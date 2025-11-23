<?php
/**
 * Taxonomy Registration
 *
 * @package GalleryQuest
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gallery Quest Taxonomies Class
 */
class Gallery_Quest_Taxonomies {
	/**
	 * Instance of this class.
	 *
	 * @var Gallery_Quest_Taxonomies|null
	 */
	private static ?Gallery_Quest_Taxonomies $instance = null;

	/**
	 * Get instance of this class.
	 *
	 * @return Gallery_Quest_Taxonomies
	 */
	public static function get_instance(): Gallery_Quest_Taxonomies {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->register_taxonomies();
		$this->register_meta_fields();
		add_filter( 'manage_media_columns', array( $this, 'add_taxonomy_columns' ) );
		add_action( 'manage_media_custom_column', array( $this, 'render_taxonomy_columns' ), 10, 2 );
		add_filter( 'attachment_fields_to_edit', array( $this, 'add_taxonomy_fields' ), 10, 2 );
		add_action( 'edit_attachment', array( $this, 'save_taxonomy_fields' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_attachment_field_assets' ) );
	}

	/**
	 * Register meta fields for attachments.
	 */
	private function register_meta_fields(): void {
		register_post_meta(
			'attachment',
			'_gallery_quest_card_number',
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => fn() => current_user_can( 'edit_posts' ),
			)
		);
	}

	/**
	 * Register taxonomies for attachment post type.
	 */
	public function register_taxonomies(): void {
		$taxonomies = array(
			'gallery_character' => array(
				'labels' => array(
					'name'          => _x( 'Characters', 'Taxonomy General Name', 'gallery-quest' ),
					'singular_name' => _x( 'Character', 'Taxonomy Singular Name', 'gallery-quest' ),
					'menu_name'     => __( 'Characters', 'gallery-quest' ),
				),
			),
			'gallery_artist'    => array(
				'labels' => array(
					'name'          => _x( 'Artists', 'Taxonomy General Name', 'gallery-quest' ),
					'singular_name' => _x( 'Artist', 'Taxonomy Singular Name', 'gallery-quest' ),
					'menu_name'     => __( 'Artists', 'gallery-quest' ),
				),
			),
			'gallery_rarity'    => array(
				'labels' => array(
					'name'          => _x( 'Rarity', 'Taxonomy General Name', 'gallery-quest' ),
					'singular_name' => _x( 'Rarity', 'Taxonomy Singular Name', 'gallery-quest' ),
					'menu_name'     => __( 'Rarity', 'gallery-quest' ),
				),
			),
		);

		foreach ( $taxonomies as $taxonomy => $args ) {
			$defaults = array(
				'hierarchical'      => false,
				'public'            => true,
				'show_ui'           => true,
				'show_admin_column' => true,
				'show_in_nav_menus' => false,
				'show_tagcloud'     => false,
				'show_in_rest'      => true,
				'rest_base'         => $taxonomy,
				'query_var'         => true,
				'rewrite'           => array( 'slug' => str_replace( 'gallery_', '', $taxonomy ) ),
			);

			register_taxonomy( $taxonomy, 'attachment', wp_parse_args( $args, $defaults ) );
		}
	}

	/**
	 * Add taxonomy columns to Media Library.
	 *
	 * @param array $columns Existing columns.
	 * @return array Modified columns.
	 */
	public function add_taxonomy_columns( $columns ) {
		$columns['gallery_character'] = __( 'Characters', 'gallery-quest' );
		$columns['gallery_artist']    = __( 'Artists', 'gallery-quest' );
		$columns['gallery_rarity']    = __( 'Rarity', 'gallery-quest' );
		$columns['gallery_quest_card_number'] = __( 'Card Number', 'gallery-quest' );
		return $columns;
	}

	/**
	 * Render taxonomy columns in Media Library.
	 *
	 * @param string $column_name Column name.
	 * @param int    $post_id     Post ID.
	 */
	public function render_taxonomy_columns( $column_name, $post_id ) {
		$taxonomies = array( 'gallery_character', 'gallery_artist', 'gallery_rarity' );

		if ( in_array( $column_name, $taxonomies, true ) ) {
			$terms = get_the_terms( $post_id, $column_name );
			if ( $terms && ! is_wp_error( $terms ) ) {
			$term_names = array_map(
				fn( $term ) => esc_html( $term->name ),
				$terms
			);
				echo esc_html( implode( ', ', $term_names ) );
			} else {
				echo '—';
			}
		} elseif ( 'gallery_quest_card_number' === $column_name ) {
			$card_number = get_post_meta( $post_id, '_gallery_quest_card_number', true );
			if ( $card_number !== '' ) {
				echo esc_html( $card_number );
			} else {
				echo '—';
			}
		}
	}

	/**
	 * Add taxonomy fields to attachment edit screen.
	 *
	 * @param array   $form_fields Form fields.
	 * @param WP_Post $post        Post object.
	 * @return array Modified form fields.
	 */
	public function add_taxonomy_fields( $form_fields, $post ) {
		$taxonomies = array(
			'gallery_character' => __( 'Characters', 'gallery-quest' ),
			'gallery_artist'    => __( 'Artists', 'gallery-quest' ),
			'gallery_rarity'    => __( 'Rarity', 'gallery-quest' ),
		);

		foreach ( $taxonomies as $taxonomy => $label ) {
			$selected_terms = get_the_terms( $post->ID, $taxonomy );
			$selected_term_ids = array();
			if ( $selected_terms && ! is_wp_error( $selected_terms ) ) {
				$selected_term_ids = array_map( fn( $term ) => $term->term_id, $selected_terms );
			}

			// Get all terms for this taxonomy
			$all_terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
				)
			);

			// Build select options
			$options = '<option value="">' . esc_html__( '— None —', 'gallery-quest' ) . '</option>';
			if ( $all_terms && ! is_wp_error( $all_terms ) ) {
				foreach ( $all_terms as $term ) {
					$selected = in_array( $term->term_id, $selected_term_ids, true ) ? 'selected' : '';
					$options .= sprintf(
						'<option value="%d" %s>%s</option>',
						esc_attr( $term->term_id ),
						$selected,
						esc_html( $term->name )
					);
				}
			}

			// Create select dropdown HTML with "Add New" option
			$field_html = sprintf(
				'<select 
					name="attachments[%d][%s][]" 
					class="gallery-quest-taxonomy-select" 
					id="gallery-quest-select-%s-%d"
					multiple="multiple"
					size="5"
					style="width: 100%%; min-height: 100px;"
				>%s</select>
				<div class="gallery-quest-add-term" style="margin-top: 8px;">
					<input 
						type="text" 
						class="gallery-quest-new-term-input" 
						data-taxonomy="%s"
						data-post-id="%d"
						placeholder="%s"
						style="width: calc(100%% - 80px); padding: 6px 8px; border: 1px solid #8c8f94; border-radius: 3px; font-size: 14px;"
					/>
					<button 
						type="button" 
						class="button button-small gallery-quest-add-term-btn" 
						data-taxonomy="%s"
						data-post-id="%d"
						style="margin-left: 4px;"
					>%s</button>
				</div>
				<p class="help">%s</p>',
				absint( $post->ID ),
				esc_attr( $taxonomy ),
				esc_attr( $taxonomy ),
				absint( $post->ID ),
				$options,
				esc_attr( $taxonomy ),
				absint( $post->ID ),
				esc_attr( sprintf( 
					/* translators: %s: Taxonomy label (lowercase) */
					__( 'Add new %s...', 'gallery-quest' ), 
					strtolower( $label ) 
				) ),
				esc_attr( $taxonomy ),
				absint( $post->ID ),
				esc_html__( 'Add', 'gallery-quest' ),
				esc_html( sprintf(
					/* translators: %s: Taxonomy name (lowercase) */
					__( 'Hold Ctrl/Cmd to select multiple %s. Use the field below to add new terms.', 'gallery-quest' ),
					strtolower( $label )
				) )
			);

			$form_fields[ $taxonomy ] = array(
				'label' => $label,
				'input' => 'html',
				'html'  => $field_html,
			);
		}

		// Add card number field
		$card_number = get_post_meta( $post->ID, '_gallery_quest_card_number', true );
		$card_number = $card_number !== '' ? esc_attr( $card_number ) : '';

		$form_fields['gallery_quest_card_number'] = array(
			'label' => __( 'Card Number', 'gallery-quest' ),
			'input' => 'html',
			'html'  => sprintf(
				'<input 
					type="text" 
					name="attachments[%d][gallery_quest_card_number]" 
					value="%s" 
					class="gallery-quest-card-number-input" 
					placeholder="%s"
				/>
				<p class="help">%s</p>',
				absint( $post->ID ),
				esc_attr( $card_number ),
				esc_attr__( 'e.g., CARD-001', 'gallery-quest' ),
				esc_html__( 'Enter the card number for this image.', 'gallery-quest' )
			),
		);

		return $form_fields;
	}

	/**
	 * Enqueue scripts and styles for attachment fields.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_attachment_field_assets( string $hook ): void {
		// TEMPORARILY DISABLED - Debugging browser lockup issue
		// Only load on media library pages and post edit screens
		if ( 'post.php' !== $hook && 'upload.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}

		// Additional check: only load if we're editing an attachment
		$screen = get_current_screen();
		if ( $screen && 'attachment' !== $screen->post_type ) {
			return;
		}

		// Only load on attachment edit screen, not media library list
		if ( $screen && 'attachment' === $screen->post_type && 'post' === $screen->base ) {
			// This is the attachment edit screen - load the script
		} else {
			// Don't load on media library list view
			return;
		}

		$script_path = GALLERY_QUEST_PLUGIN_DIR . 'admin/js/attachment-fields.js';
		$script_url  = GALLERY_QUEST_PLUGIN_URL . 'admin/js/attachment-fields.js';

		if ( file_exists( $script_path ) ) {
			wp_enqueue_script(
				'gallery-quest-attachment-fields',
				$script_url,
				array(),
				GALLERY_QUEST_VERSION,
				true
			);

			wp_localize_script(
				'gallery-quest-attachment-fields',
				'galleryQuestAttachmentFields',
				array(
					'restUrl' => esc_url_raw( rest_url() ),
					'nonce'   => wp_create_nonce( 'wp_rest' ),
				)
			);
		}

		$style_path = GALLERY_QUEST_PLUGIN_DIR . 'admin/css/attachment-fields.css';
		$style_url  = GALLERY_QUEST_PLUGIN_URL . 'admin/css/attachment-fields.css';

		if ( file_exists( $style_path ) ) {
			wp_enqueue_style(
				'gallery-quest-attachment-fields',
				$style_url,
				array(),
				GALLERY_QUEST_VERSION
			);
		}
	}

	/**
	 * Save taxonomy fields from attachment edit screen.
	 *
	 * @param int $post_id Post ID.
	 */
	public function save_taxonomy_fields( $post_id ) {
		try {
			// Check if we have the compat nonce - this confirms we're in a context that supports this save method.
			if ( ! isset( $_POST['save-attachment-compat'] ) ) {
				return;
			}

			// Verify nonce for attachment edit form.
			// Use safe verify to prevent crashes if input is malformed
			$nonce = isset( $_POST['save-attachment-compat'] ) ? sanitize_text_field( wp_unslash( $_POST['save-attachment-compat'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'update-attachment_' . $post_id ) ) {
				return;
			}

			// Check user capabilities.
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return;
			}

			// Ensure attachments array exists in POST
			if ( ! isset( $_POST['attachments'] ) || ! is_array( $_POST['attachments'] ) ) {
				return;
			}

			// Ensure specific post data exists
			if ( ! isset( $_POST['attachments'][ $post_id ] ) || ! is_array( $_POST['attachments'][ $post_id ] ) ) {
				return;
			}

			$taxonomies = array( 'gallery_character', 'gallery_artist', 'gallery_rarity' );

			foreach ( $taxonomies as $taxonomy ) {
				if ( isset( $_POST['attachments'][ $post_id ][ $taxonomy ] ) ) {
					$input_value = wp_unslash( $_POST['attachments'][ $post_id ][ $taxonomy ] );
					
					// Handle array input (multiple select)
					if ( is_array( $input_value ) ) {
						// Sanitize array of IDs
						$term_ids = array_map( 'absint', $input_value );
						$term_ids = array_filter( $term_ids ); // Remove 0s and empty values

						if ( ! empty( $term_ids ) ) {
							wp_set_object_terms( $post_id, $term_ids, $taxonomy );
						} else {
							wp_set_object_terms( $post_id, array(), $taxonomy );
						}
					} 
					// Handle single value fallback
					else {
						// Sanitize single ID
						$term_id = absint( $input_value );
						if ( $term_id > 0 ) {
							wp_set_object_terms( $post_id, array( $term_id ), $taxonomy );
						} else {
							wp_set_object_terms( $post_id, array(), $taxonomy );
						}
					}
				}
			}

			// Save card number
			if ( isset( $_POST['attachments'][ $post_id ]['gallery_quest_card_number'] ) ) {
				$card_number = sanitize_text_field( wp_unslash( $_POST['attachments'][ $post_id ]['gallery_quest_card_number'] ) );
				if ( $card_number !== '' ) {
					update_post_meta( $post_id, '_gallery_quest_card_number', $card_number );
				} else {
					delete_post_meta( $post_id, '_gallery_quest_card_number' );
				}
			}
		} catch ( Throwable $t ) {
			// Silently fail on errors to prevent 500 response
			// In development, you might want to log this: error_log( $t->getMessage() );
			return;
		} catch ( Exception $e ) {
			// Fallback for PHP < 7
			return;
		}
	}
}
