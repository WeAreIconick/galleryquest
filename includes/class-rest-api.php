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
		try {
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
		// Include a version number from post meta to allow easy invalidation.
		$cache_version = get_post_meta( $gallery_id, '_gallery_quest_cache_version', true );
		if ( empty( $cache_version ) ) {
			$cache_version = 1;
			update_post_meta( $gallery_id, '_gallery_quest_cache_version', $cache_version );
		}

		$cache_params = array(
			'gallery_id'   => $gallery_id,
			'version'      => $cache_version,
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

			// Filter attachment IDs by taxonomy if needed
			$filtered_ids = $attachment_ids;
			$has_filters  = false;
			$filters      = array(
				'character' => 'gallery_character',
				'artist'    => 'gallery_artist',
				'rarity'    => 'gallery_rarity',
			);

			// Collect all term matches
			$term_matches = array();

			foreach ( $filters as $param => $taxonomy ) {
				$value = sanitize_text_field( $request[ $param ] );
				if ( ! empty( $value ) ) {
					$has_filters = true;
					$terms = array_filter( array_map( 'trim', explode( ',', $value ) ) );
					
					if ( ! empty( $terms ) ) {
						// Get object IDs for these terms directly to avoid tax_query (slow query warning)
						// We need term IDs for get_objects_in_term, or use get_posts but that's recursive logic.
						// get_objects_in_term requires term IDs. So we resolve slugs to IDs first.
						$term_ids = array();
						foreach ( $terms as $term_slug ) {
							$term_obj = get_term_by( 'slug', $term_slug, $taxonomy );
							if ( $term_obj ) {
								$term_ids[] = $term_obj->term_id;
							}
						}

						if ( ! empty( $term_ids ) ) {
							$term_matches[ $taxonomy ] = get_objects_in_term( $term_ids, $taxonomy );
						} else {
							// Filter set but no valid terms found -> no results for this filter
							$term_matches[ $taxonomy ] = array();
						}
					}
				}
			}

			if ( $has_filters ) {
				$filter_logic = sanitize_key( $request['filterLogic'] );
				
				if ( 'AND' === $filter_logic ) {
					// Intersect all term matches with existing IDs
					foreach ( $term_matches as $ids ) {
						$filtered_ids = array_intersect( $filtered_ids, $ids );
					}
					// If a filter was set but returned no matches, the intersection is empty
					if ( count( $term_matches ) < count( array_filter( $filters, fn($p) => !empty( $request[$p] ), ARRAY_FILTER_USE_KEY ) ) ) {
						// This logic handles if a filter was provided but resolved to 0 IDs
						// However, we iterate term_matches which only has entries for found terms.
						// If a filter param was non-empty but no terms found, we need to handle empty result.
						// The simpler way is checking term_matches count vs non-empty request params.
						// Actually, if we added empty arrays to term_matches for not-found terms, it works.
						// Let's assume term_matches contains an entry for every active filter.
					}
				} else {
					// OR logic: Union of all term matches, then intersect with gallery IDs
					if ( ! empty( $term_matches ) ) {
						$all_matches = array();
						foreach ( $term_matches as $ids ) {
							$all_matches = array_merge( $all_matches, $ids );
						}
						$all_matches = array_unique( $all_matches );
						$filtered_ids = array_intersect( $filtered_ids, $all_matches );
					}
				}
			}

			// If filters resulted in no images
			if ( empty( $filtered_ids ) ) {
				return rest_ensure_response(
					array(
						'items' => array(),
						'total' => 0,
						'pages' => 0,
					)
				);
			}

			// Update query args with filtered IDs
			$query_args['post__in'] = $filtered_ids;

			// Query attachments.
			$query = new WP_Query( $query_args );

			// Format response.
			$items = array();
			if ( $query->have_posts() ) {
				foreach ( $query->posts as $attachment ) {
					$items[] = $this->format_image_data( $attachment );
				}
				
				// Apply pagination manually if needed, but WP_Query handles it via 'paged' and 'posts_per_page'
				// The previous code had manual slicing which is redundant/incorrect if WP_Query is doing it.
				// However, 'post__in' with 'orderby' => 'post__in' ignores 'posts_per_page' in some older WP versions 
				// or specific contexts, but usually it works.
				// Wait, 'post__in' DOES NOT ignore pagination.
				// BUT if we filter by taxonomy, 'post__in' is just a constraint.
			}

			// Recalculate total and pages.
			// WP_Query gives us found_posts if we don't use no_found_rows => true
			// But we set no_found_rows => false above, so we should use it.
			$total_items = $query->found_posts;
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

		} catch ( Exception $e ) {
			return new WP_Error(
				'gallery_quest_api_error',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
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

		// Increment the cache version. This effectively invalidates all existing transients
		// for this gallery because the cache key includes this version number.
		// Old transients will expire naturally via WordPress garbage collection.
		$current_version = (int) get_post_meta( $post_id, '_gallery_quest_cache_version', true );
		update_post_meta( $post_id, '_gallery_quest_cache_version', $current_version + 1 );

		// Clear object cache for the post as well.
		clean_post_cache( $post_id );
	}
}

