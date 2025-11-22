<?php
/**
 * Block Editor Meta Panel
 *
 * @package GalleryQuest
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gallery Quest Meta Panel Class
 */
class Gallery_Quest_Meta_Panel {
	/**
	 * Instance of this class.
	 *
	 * @var Gallery_Quest_Meta_Panel|null
	 */
	private static ?Gallery_Quest_Meta_Panel $instance = null;

	/**
	 * Get instance of this class.
	 *
	 * @return Gallery_Quest_Meta_Panel
	 */
	public static function get_instance(): Gallery_Quest_Meta_Panel {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		// Register meta field after post type is registered
		add_action( 'init', array( $this, 'register_meta_field' ), 20 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
	}

	/**
	 * Register meta field for gallery images.
	 */
	public function register_meta_field(): void {
		// Ensure post type exists before registering meta
		if ( ! post_type_exists( 'gallery_quest' ) ) {
			return;
		}

		// Register post meta with schema for REST API support
		register_post_meta(
			'gallery_quest',
			'_gallery_quest_images',
			array(
				'show_in_rest'      => array(
					'schema' => array(
						'type'  => 'array',
						'items' => array(
							'type' => 'integer',
						),
					),
				),
				'single'            => true,
				'type'              => 'array',
				'sanitize_callback' => function( $value ) {
					if ( ! is_array( $value ) ) {
						return array();
					}
					$sanitized = array_map( 'absint', $value );
					return array_values( array_filter( $sanitized ) );
				},
				'auth_callback'     => function() {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}

	/**
	 * Enqueue block editor assets.
	 */
	public function enqueue_editor_assets(): void {
		$screen = get_current_screen();
		if ( ! $screen || 'gallery_quest' !== $screen->post_type ) {
			return;
		}

		$asset_file_path = GALLERY_QUEST_PLUGIN_DIR . 'build/gallery-meta-panel.asset.php';
		$asset_file      = file_exists( $asset_file_path ) ? include $asset_file_path : false;

		if ( ! $asset_file ) {
			// Fallback if asset file doesn't exist yet.
			$asset_file = array(
				'dependencies' => array(
					'wp-plugins',
					'wp-editor',
					'wp-element',
					'wp-components',
					'wp-data',
					'wp-block-editor',
					'wp-api-fetch',
					'wp-i18n',
				),
				'version'      => GALLERY_QUEST_VERSION,
			);
		}

		$script_path = GALLERY_QUEST_PLUGIN_DIR . 'build/gallery-meta-panel.js';
		$script_url  = GALLERY_QUEST_PLUGIN_URL . 'build/gallery-meta-panel.js';

		// Only enqueue if file exists (after build).
		if ( file_exists( $script_path ) ) {
			wp_enqueue_script(
				'gallery-quest-meta-panel',
				$script_url,
				$asset_file['dependencies'],
				$asset_file['version'],
				true
			);
		}
	}

}
