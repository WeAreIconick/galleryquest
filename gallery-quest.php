<?php
/**
 * Plugin Name: Gallery Quest
 * Plugin URI: https://iconick.io
 * Description: A filterable gallery block plugin for WordPress. Each gallery post contains multiple images, and each image can have its own taxonomies for filtering.
 * Version: 1.0.1
 * Author: Iconick
 * Author URI: https://iconick.io
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: gallery-quest
 * Requires at least: 6.1
 * Requires PHP: 7.4
 *
 * @package GalleryQuest
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'GALLERY_QUEST_VERSION', '1.0.2' );
define( 'GALLERY_QUEST_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GALLERY_QUEST_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main plugin class.
 */
class Gallery_Quest {
	/**
	 * Instance of this class.
	 *
	 * @var Gallery_Quest|null
	 */
	private static ?Gallery_Quest $instance = null;

	/**
	 * Get instance of this class.
	 *
	 * @return Gallery_Quest
	 */
	public static function get_instance(): Gallery_Quest {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->init();
	}

	/**
	 * Initialize the plugin.
	 */
	private function init(): void {
		// Include required files.
		$this->includes();

		// Initialize components.
		add_action( 'init', array( $this, 'init_components' ) );
	}

	/**
	 * Include required files.
	 */
	private function includes(): void {
		$includes = array(
			'includes/class-post-type.php',
			'includes/class-taxonomies.php',
			'includes/class-rest-api.php',
			'includes/class-image-sizes.php',
			'admin/class-meta-panel.php',
		);

		foreach ( $includes as $file ) {
			$file_path = GALLERY_QUEST_PLUGIN_DIR . $file;
			if ( file_exists( $file_path ) ) {
				require_once $file_path;
			} else {
				// File not found - silently fail to prevent breaking the site.
				// In development, check WP_DEBUG_LOG for missing files.
			}
		}
	}

	/**
	 * Initialize plugin components.
	 */
	public function init_components(): void {
		// Register custom post type.
		if ( class_exists( 'Gallery_Quest_Post_Type' ) ) {
			Gallery_Quest_Post_Type::get_instance();
		}

		// Register taxonomies.
		if ( class_exists( 'Gallery_Quest_Taxonomies' ) ) {
			Gallery_Quest_Taxonomies::get_instance();
		}

		// Register REST API endpoints.
		if ( class_exists( 'Gallery_Quest_REST_API' ) ) {
			Gallery_Quest_REST_API::get_instance();
		}

		// Register image sizes.
		if ( class_exists( 'Gallery_Quest_Image_Sizes' ) ) {
			Gallery_Quest_Image_Sizes::get_instance();
		}

		// Initialize admin meta panel.
		if ( class_exists( 'Gallery_Quest_Meta_Panel' ) ) {
			Gallery_Quest_Meta_Panel::get_instance();
		}

		// Register block from build directory.
		$block_path = GALLERY_QUEST_PLUGIN_DIR . 'build';
		if ( file_exists( $block_path . '/block.json' ) ) {
			register_block_type( $block_path );
		}

		// Localize script for frontend.
		add_action( 'wp_enqueue_scripts', array( $this, 'localize_frontend_script' ) );
		
		// Manually enqueue view styles as fallback/guarantee
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_styles' ) );
	}

	/**
	 * Enqueue frontend styles and scripts manually.
	 */
	public function enqueue_frontend_styles(): void {
		if ( has_block( 'gallery-quest/gallery-quest' ) ) {
			$view_css_path = GALLERY_QUEST_PLUGIN_DIR . 'build/view.css';
			$view_css_url  = GALLERY_QUEST_PLUGIN_URL . 'build/view.css';

			if ( file_exists( $view_css_path ) ) {
				wp_enqueue_style(
					'gallery-quest-view-style-manual',
					$view_css_url,
					array(),
					GALLERY_QUEST_VERSION
				);
			}

			// Manually enqueue view script if not loaded by block.json
			$view_js_path = GALLERY_QUEST_PLUGIN_DIR . 'build/view.js';
			$view_js_url  = GALLERY_QUEST_PLUGIN_URL . 'build/view.js';
			$asset_file   = include GALLERY_QUEST_PLUGIN_DIR . 'build/view.asset.php';

			if ( file_exists( $view_js_path ) ) {
				wp_enqueue_script(
					'gallery-quest-view-script-manual',
					$view_js_url,
					$asset_file['dependencies'],
					GALLERY_QUEST_VERSION,
					true
				);
			}
		}
	}

	/**
	 * Localize frontend script with REST API data.
	 */
	public function localize_frontend_script(): void {
		// WordPress generates handle as: {namespace}-{block-name}-view-script
		$handle = 'gallery-quest-gallery-quest-view-script';
		
		if ( wp_script_is( $handle, 'enqueued' ) || wp_script_is( $handle, 'registered' ) ) {
			wp_localize_script(
				$handle,
				'galleryQuestData',
				array(
					'restUrl' => esc_url_raw( rest_url( 'gallery-quest/v1/' ) ),
					'nonce'   => wp_create_nonce( 'wp_rest' ),
				)
			);
		}
	}
}

/**
 * Initialize the plugin.
 */
function gallery_quest_init() {
	return Gallery_Quest::get_instance();
}

// Start the plugin.
gallery_quest_init();
