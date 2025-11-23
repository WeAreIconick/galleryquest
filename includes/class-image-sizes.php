<?php
/**
 * Custom Image Sizes
 *
 * @package GalleryQuest
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gallery Quest Image Sizes Class
 */
class Gallery_Quest_Image_Sizes {
	/**
	 * Instance of this class.
	 *
	 * @var Gallery_Quest_Image_Sizes|null
	 */
	private static ?Gallery_Quest_Image_Sizes $instance = null;

	/**
	 * Get instance of this class.
	 *
	 * @return Gallery_Quest_Image_Sizes
	 */
	public static function get_instance(): Gallery_Quest_Image_Sizes {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'after_setup_theme', array( $this, 'register_image_sizes' ) );
		add_filter( 'image_size_names_choose', array( $this, 'add_image_size_names' ) );
	}

	/**
	 * Register custom image sizes.
	 */
	public function register_image_sizes(): void {
		add_image_size( 'gallery-thumb', 300, 9999, false ); // Soft crop, width restricted.
		add_image_size( 'gallery-medium', 600, 9999, false ); // Soft crop, width restricted.
		add_image_size( 'gallery-large', 1200, 9999, false ); // Soft crop, width restricted.
	}

	/**
	 * Add custom image sizes to media library dropdown.
	 *
	 * @param array $sizes Existing image sizes.
	 * @return array Modified image sizes.
	 */
	public function add_image_size_names( array $sizes ): array {
		return array_merge(
			$sizes,
			array(
				'gallery-thumb'  => __( 'Gallery Thumbnail', 'gallery-quest' ),
				'gallery-medium' => __( 'Gallery Medium', 'gallery-quest' ),
				'gallery-large'  => __( 'Gallery Large', 'gallery-quest' ),
			)
		);
	}
}

