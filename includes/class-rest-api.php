<?php
/**
 * REST API Endpoints
 *
 * @package GalleryQuest
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gallery Quest REST API Class
 */
class Gallery_Quest_REST_API {
	/**
	 * Instance of this class.
	 *
	 * @var Gallery_Quest_REST_API|null
	 */
	private static ?Gallery_Quest_REST_API $instance = null;

	/**
	 * Get instance of this class.
	 *
	 * @return Gallery_Quest_REST_API
	 */
	public static function get_instance(): Gallery_Quest_REST_API {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'save_post_gallery_quest', array( $this, 'invalidate_cache' ) );
		add_action( 'delete_post', array( $this, 'invalidate_cache' ) );
	}

	/**
	 * Register REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			'gallery-quest/v1',
			'/images/(?P<galleryId>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_gallery_images' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'galleryId'  => array(
						'required'          => true,
						'validate_callback' => fn( $param ) => is_numeric( $param ) && $param > 0,
						'sanitize_callback' => 'absint',
					),
					'character'  => array(
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'artist'     => array(
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'rarity'     => array(
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'page'       => array(
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'per_page'   => array(
						'default'           => 20,
						'validate_callback' => fn( $param ) => $param >= 1 && $param <= 100,
						'sanitize_callback' => 'absint',
					),
					'filterLogic' => array(
						'default'           => 'OR',
						'validate_callback' => fn( $param ) => in_array( $param, array( 'AND', 'OR' ), true ),
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);
	}
	
	/**
	 * Get filtered images for a gallery.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function get_gallery_images( $request ) {
		$gallery_id = absint( $request['galleryId'] );

		// Get gallery post.
		$gallery = get_post( $gallery_id );
		if ( ! $gallery || 'gallery_quest' !== $gallery->post_type ) {
			return new WP_Error(
				'gallery_not_found',
				__( 'Gallery not found', 'gallery-quest' ),
				array( 'status' => 404 )
			);
		}

		// Get attachment IDs from post meta.
		$attachment_ids = get_post_meta( $gallery_id, '_gallery_quest_images', true );
		if ( ! is_array( $attachment_ids ) || empty( $attachment_ids ) ) {
			return rest_ensure_response(
				array(
					'items' => array(),
					'total' => 0,
					'pages' => 0,
				)
			);
		}

		// Sanitize attachment IDs.
		$attachment_ids = array_map( 'absint', $attachment_ids );
		$attachment_ids = array_filter( $attachment_ids );

		if ( empty( $attachment_ids ) ) {
			return rest_ensure_response(
				array(
					'items' => array(),
					'total' => 0,
					'pages' => 0,
				)
			);
		}

		// Build cache key.
		$cache_params = array(
			'gallery_id'   => $gallery_id,
			'attachment_ids' => $attachment_ids,
			'character'    => $request['character'],
			'artist'       => $request['artist'],
			'rarity'       => $request['rarity'],
			'page'         => $request['page'],
			'per_page'     => $request['per_page'],
			'filterLogic'  => $request['filterLogic'],
		);
		$cache_key    = 'gallery_quest_images_' . md5( serialize( $cache_params ) );

		// Try to get cached data.
		$cached = get_transient( $cache_key );
		if ( false !== $cached ) {
			return rest_ensure_response( $cached );
		}

		// Build query args.
		$query_args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post__in'       => $attachment_ids,
			'posts_per_page' => absint( $request['per_page'] ),
			'paged'          => absint( $request['page'] ),
			'orderby'        => 'post__in',
			'order'          => 'ASC',
			'no_found_rows'  => false,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => true,
		);

		// Build tax_query if filters are active.
		$tax_query = array();
		$filters   = array(
			'character' => 'gallery_character',
			'artist'    => 'gallery_artist',
			'rarity'    => 'gallery_rarity',
		);

		foreach ( $filters as $param => $taxonomy ) {
			$value = sanitize_text_field( $request[ $param ] );
			if ( ! empty( $value ) ) {
				$terms = array_filter( array_map( 'trim', explode( ',', $value ) ) );
				if ( ! empty( $terms ) ) {
					$tax_query[] = array(
						'taxonomy' => $taxonomy,
						'field'    => 'slug',
						'terms'    => $terms,
						'operator' => 'IN',
					);
				}
			}
		}

		if ( ! empty( $tax_query ) ) {
			$filter_logic = sanitize_key( $request['filterLogic'] );
			$tax_query['relation'] = ( 'AND' === $filter_logic ) ? 'AND' : 'OR';
			$query_args['tax_query'] = $tax_query;
		}

		// Query attachments.
		$query = new WP_Query( $query_args );

		// Format response.
		$items = array();
		if ( $query->have_posts() ) {
			foreach ( $query->posts as $attachment ) {
				$items[] = $this->format_image_data( $attachment );
			}

			// Apply pagination after sorting.
			$per_page = absint( $request['per_page'] );
			$page     = absint( $request['page'] );
			$offset   = ( $page - 1 ) * $per_page;
			$items    = array_slice( $items, $offset, $per_page );
		}

		// Recalculate total and pages after sorting.
		$total_items = count( $query->posts );
		$per_page    = absint( $request['per_page'] );
		$total_pages = ceil( $total_items / $per_page );

		$response = array(
			'items' => $items,
			'total' => $total_items,
			'pages' => $total_pages,
		);

		// Cache the response for 15 minutes.
		set_transient( $cache_key, $response, 15 * MINUTE_IN_SECONDS );

		return rest_ensure_response( $response );
	}

	/**
	 * Format image data for API response.
	 *
	 * @param WP_Post $attachment Attachment post object.
	 * @return array Formatted image data.
	 */
	private function format_image_data( $attachment ) {
		$card_number = get_post_meta( $attachment->ID, '_gallery_quest_card_number', true );
		$card_number = $card_number !== '' ? $card_number : '';

		$image_data = array(
			'id'    => $attachment->ID,
			'title' => get_the_title( $attachment->ID ),
			'alt'   => get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true ),
			'card_number' => $card_number,
			'urls'  => array(
				'thumb'  => wp_get_attachment_image_url( $attachment->ID, 'gallery-thumb' ),
				'medium' => wp_get_attachment_image_url( $attachment->ID, 'gallery-medium' ),
				'large'  => wp_get_attachment_image_url( $attachment->ID, 'gallery-large' ),
				'full'   => wp_get_attachment_image_url( $attachment->ID, 'full' ),
			),
			'taxonomies' => array(),
		);

		// Get taxonomy terms.
		$taxonomies = array( 'gallery_character', 'gallery_artist', 'gallery_rarity' );
		foreach ( $taxonomies as $taxonomy ) {
			$terms = get_the_terms( $attachment->ID, $taxonomy );
			if ( $terms && ! is_wp_error( $terms ) ) {
				$image_data['taxonomies'][ $taxonomy ] = array_map(
					fn( $term ) => array(
						'id'   => $term->term_id,
						'name' => $term->name,
						'slug' => $term->slug,
					),
					$terms
				);
			} else {
				$image_data['taxonomies'][ $taxonomy ] = array();
			}
		}

		return $image_data;
	}

	/**
	 * Invalidate cache when gallery is updated or deleted.
	 *
	 * @param int $post_id Post ID.
	 */
	public function invalidate_cache( $post_id ) {
		if ( 'gallery_quest' !== get_post_type( $post_id ) ) {
			return;
		}

		// Clear object cache first.
		wp_cache_delete( 'gallery_quest_images_' . $post_id, 'gallery_quest' );

		// Delete transients for this gallery using WordPress functions where possible.
		// Note: WordPress doesn't provide a built-in way to delete transients by pattern,
		// so we use direct DB query as a fallback, but only when necessary.
		$cache_group = 'gallery_quest_images_' . $post_id;
		delete_transient( $cache_group );

		// For pattern-based deletion, we need to use direct query.
		// This is acceptable for cache invalidation on post save/delete.
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_gallery_quest_images_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_gallery_quest_images_' ) . '%'
			)
		);

		// Clear any object cache.
		wp_cache_flush_group( 'gallery_quest' );
	}
}

