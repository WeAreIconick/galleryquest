<?php
/**
 * Custom Post Type Registration
 *
 * @package GalleryQuest
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gallery Quest Post Type Class
 */
class Gallery_Quest_Post_Type {
	/**
	 * Instance of this class.
	 *
	 * @var Gallery_Quest_Post_Type|null
	 */
	private static ?Gallery_Quest_Post_Type $instance = null;

	/**
	 * Get instance of this class.
	 *
	 * @return Gallery_Quest_Post_Type
	 */
	public static function get_instance(): Gallery_Quest_Post_Type {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->register_post_type();
	}

	/**
	 * Register the gallery_quest custom post type.
	 */
	public function register_post_type(): void {
		$labels = array(
			'name'                  => _x( 'Galleries', 'Post Type General Name', 'gallery-quest' ),
			'singular_name'         => _x( 'Gallery', 'Post Type Singular Name', 'gallery-quest' ),
			'menu_name'             => __( 'Galleries', 'gallery-quest' ),
			'name_admin_bar'        => __( 'Gallery', 'gallery-quest' ),
			'archives'              => __( 'Gallery Archives', 'gallery-quest' ),
			'attributes'            => __( 'Gallery Attributes', 'gallery-quest' ),
			'parent_item_colon'     => __( 'Parent Gallery:', 'gallery-quest' ),
			'all_items'             => __( 'All Galleries', 'gallery-quest' ),
			'add_new_item'          => __( 'Add New Gallery', 'gallery-quest' ),
			'add_new'               => __( 'Add New', 'gallery-quest' ),
			'new_item'              => __( 'New Gallery', 'gallery-quest' ),
			'edit_item'             => __( 'Edit Gallery', 'gallery-quest' ),
			'update_item'           => __( 'Update Gallery', 'gallery-quest' ),
			'view_item'             => __( 'View Gallery', 'gallery-quest' ),
			'view_items'            => __( 'View Galleries', 'gallery-quest' ),
			'search_items'          => __( 'Search Gallery', 'gallery-quest' ),
			'not_found'             => __( 'Not found', 'gallery-quest' ),
			'not_found_in_trash'    => __( 'Not found in Trash', 'gallery-quest' ),
			'featured_image'        => __( 'Featured Image', 'gallery-quest' ),
			'set_featured_image'    => __( 'Set featured image', 'gallery-quest' ),
			'remove_featured_image' => __( 'Remove featured image', 'gallery-quest' ),
			'use_featured_image'    => __( 'Use as featured image', 'gallery-quest' ),
			'insert_into_item'      => __( 'Insert into gallery', 'gallery-quest' ),
			'uploaded_to_this_item' => __( 'Uploaded to this gallery', 'gallery-quest' ),
			'items_list'            => __( 'Galleries list', 'gallery-quest' ),
			'items_list_navigation' => __( 'Galleries list navigation', 'gallery-quest' ),
			'filter_items_list'     => __( 'Filter galleries list', 'gallery-quest' ),
		);

		$args = array(
			'label'                 => __( 'Gallery', 'gallery-quest' ),
			'description'           => __( 'Gallery collections containing multiple images', 'gallery-quest' ),
			'labels'                => $labels,
			'supports'              => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
			'hierarchical'          => false,
			'public'                => true,
			'show_ui'               => true,
			'show_in_menu'          => true,
			'menu_position'         => 20,
			'menu_icon'             => 'dashicons-format-gallery',
			'show_in_admin_bar'     => true,
			'show_in_nav_menus'     => false,
			'can_export'            => true,
			'has_archive'           => false,
			'exclude_from_search'   => true,
			'publicly_queryable'    => false,
			'capability_type'       => 'post',
			'show_in_rest'          => true,
			'rest_base'             => 'gallery-quest',
			'rest_controller_class' => 'WP_REST_Posts_Controller',
		);

		register_post_type( 'gallery_quest', $args );
	}
}

