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
	 * Pending meta to save after insert.
	 *
	 * @var array|null
	 */
	private ?array $pending_meta_save = null;

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
		// Register meta field after post type is registered (priority 20 ensures it runs after post type registration)
		add_action( 'init', array( $this, 'register_meta_field' ), 20 );
		// Also register on rest_api_init to ensure it's available when REST API initializes
		add_action( 'rest_api_init', array( $this, 'register_meta_field' ), 10 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
	}
	
	/**
	 * Hook into all REST requests to catch saves.
	 */
	public function hook_into_all_rest_requests(): void {
		// Hook into the REST server's dispatch method
		add_filter( 'rest_dispatch_request', array( $this, 'catch_all_rest_dispatches' ), 10, 4 );
	}
	
	/**
	 * Catch all REST API dispatches.
	 *
	 * @param mixed            $result  Response to replace the requested version with.
	 * @param WP_REST_Request  $request Request used to generate the response.
	 * @param string           $route   Route matched for the request.
	 * @param array            $handler Route handler used for the request.
	 * @return mixed Modified result.
	 */
	public function catch_all_rest_dispatches( $result, $request, $route, $handler ): mixed {
		// Log ALL POST/PUT/PATCH requests
		if ( in_array( $request->get_method(), array( 'POST', 'PUT', 'PATCH' ), true ) ) {
			error_log( "Gallery Quest: catch_all_rest_dispatches - Route: {$route}, Method: {$request->get_method()}" );
			
			// Check if it's a gallery-quest save
			if ( preg_match( '#/wp/v2/gallery-quest/(\d+)#', $route, $matches ) ) {
				$post_id = (int) $matches[1];
				
				// Try multiple ways to get meta
				$meta = $request->get_param( 'meta' );
				$body_params = $request->get_body_params();
				$json_params = $request->get_json_params();
				
				error_log( "Gallery Quest: Gallery quest save detected! Post ID: {$post_id}" );
				error_log( 'Gallery Quest: Meta param: ' . print_r( $meta, true ) );
				error_log( 'Gallery Quest: Body params: ' . print_r( $body_params, true ) );
				error_log( 'Gallery Quest: JSON params: ' . print_r( $json_params, true ) );
				error_log( 'Gallery Quest: All params: ' . print_r( $request->get_params(), true ) );
				
				// Try to get meta from any source
				$attachment_ids = null;
				if ( isset( $meta['_gallery_quest_images'] ) ) {
					$attachment_ids = $meta['_gallery_quest_images'];
				} elseif ( isset( $body_params['meta']['_gallery_quest_images'] ) ) {
					$attachment_ids = $body_params['meta']['_gallery_quest_images'];
				} elseif ( isset( $json_params['meta']['_gallery_quest_images'] ) ) {
					$attachment_ids = $json_params['meta']['_gallery_quest_images'];
				}
				
				if ( null !== $attachment_ids ) {
					if ( ! is_array( $attachment_ids ) ) {
						$attachment_ids = array();
					}
					$sanitized = array_map( 'absint', $attachment_ids );
					$sanitized = array_values( array_filter( $sanitized ) );
					
					// Save immediately
					$save_result = update_post_meta( $post_id, '_gallery_quest_images', $sanitized );
					error_log( 'Gallery Quest: Saved meta in catch_all_rest_dispatches: ' . print_r( $sanitized, true ) . ', Result: ' . ( $save_result ? 'true' : 'false' ) );
					
					// Verify it was saved
					$saved = get_post_meta( $post_id, '_gallery_quest_images', true );
					error_log( 'Gallery Quest: Verified saved meta: ' . print_r( $saved, true ) );
				} else {
					error_log( 'Gallery Quest: _gallery_quest_images NOT found in any source. Meta keys: ' . print_r( array_keys( $meta ? $meta : array() ), true ) );
				}
			}
		}
		
		return $result;
	}
	
	/**
	 * Log all REST API hooks for debugging.
	 */
	public function log_all_rest_hooks(): void {
		global $wp_filter;
		$hooks = array(
			'rest_insert_gallery_quest',
			'rest_after_insert_gallery_quest',
			'rest_pre_insert_gallery_quest',
			'rest_insert_gallery-quest',
			'rest_after_insert_gallery-quest',
			'rest_pre_insert_gallery-quest',
		);
		foreach ( $hooks as $hook ) {
			if ( isset( $wp_filter[ $hook ] ) ) {
				error_log( "Gallery Quest: Hook {$hook} has " . count( $wp_filter[ $hook ]->callbacks ) . " callbacks" );
			}
		}
	}
	
	/**
	 * Intercept REST API request before callbacks.
	 *
	 * @param WP_HTTP_Response|WP_Error $response Response object.
	 * @param array                     $handler  Route handler.
	 * @param WP_REST_Request           $request  Request object.
	 * @return WP_HTTP_Response|WP_Error Modified response.
	 */
	public function intercept_rest_request( $response, $handler, $request ): mixed {
		$route = $request->get_route();
		
		// Log ALL REST requests to gallery-quest to see what's happening
		if ( strpos( $route, '/gallery-quest' ) !== false ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( "Gallery Quest: intercept_rest_request - Route: {$route}, Method: {$request->get_method()}" );
			}
		}
		
		// Check if this is a gallery_quest save request
		if ( preg_match( '#/wp/v2/gallery-quest/(\d+)#', $route, $matches ) && in_array( $request->get_method(), array( 'POST', 'PUT', 'PATCH' ), true ) ) {
			$post_id = (int) $matches[1];
			$meta    = $request->get_param( 'meta' );
			
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( "Gallery Quest: intercept_rest_request - Route: {$route}, Method: {$request->get_method()}, Post ID: {$post_id}" );
				error_log( 'Gallery Quest: All request params: ' . print_r( $request->get_params(), true ) );
				error_log( 'Gallery Quest: Meta in intercepted request: ' . print_r( $meta, true ) );
			}
			
			// Save meta directly if present
			if ( isset( $meta['_gallery_quest_images'] ) ) {
				$attachment_ids = $meta['_gallery_quest_images'];
				if ( ! is_array( $attachment_ids ) ) {
					$attachment_ids = array();
				}
				$sanitized = array_map( 'absint', $attachment_ids );
				$sanitized = array_values( array_filter( $sanitized ) );
				
				update_post_meta( $post_id, '_gallery_quest_images', $sanitized );
				
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'Gallery Quest: Saved meta via intercept: ' . print_r( $sanitized, true ) );
				}
			} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Gallery Quest: _gallery_quest_images NOT in meta. Available keys: ' . print_r( array_keys( $meta ? $meta : array() ), true ) );
			}
		}
		
		return $response;
	}
	
	/**
	 * Intercept REST API pre-dispatch.
	 *
	 * @param mixed            $result  Response to replace the requested version with.
	 * @param WP_REST_Server   $server  Server instance.
	 * @param WP_REST_Request  $request Request used to generate the response.
	 * @return mixed Modified result.
	 */
	public function intercept_rest_pre_dispatch( $result, $server, $request ): mixed {
		$route = $request->get_route();
		
		// Log ALL REST requests to gallery-quest
		if ( strpos( $route, '/gallery-quest' ) !== false ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( "Gallery Quest: intercept_rest_pre_dispatch - Route: {$route}, Method: {$request->get_method()}" );
			}
		}
		
		if ( preg_match( '#/wp/v2/gallery-quest/(\d+)#', $route, $matches ) && in_array( $request->get_method(), array( 'POST', 'PUT', 'PATCH' ), true ) ) {
			$post_id = (int) $matches[1];
			
			// Try multiple ways to get the data
			$meta = $request->get_param( 'meta' );
			$body_params = $request->get_body_params();
			$json_params = $request->get_json_params();
			
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( "Gallery Quest: intercept_rest_pre_dispatch - Route: {$route}, Post ID: {$post_id}" );
				error_log( 'Gallery Quest: Meta param: ' . print_r( $meta, true ) );
				error_log( 'Gallery Quest: Body params: ' . print_r( $body_params, true ) );
				error_log( 'Gallery Quest: JSON params: ' . print_r( $json_params, true ) );
				error_log( 'Gallery Quest: All params: ' . print_r( $request->get_params(), true ) );
			}
			
			// Try to get meta from any source
			$attachment_ids = null;
			if ( isset( $meta['_gallery_quest_images'] ) ) {
				$attachment_ids = $meta['_gallery_quest_images'];
			} elseif ( isset( $body_params['meta']['_gallery_quest_images'] ) ) {
				$attachment_ids = $body_params['meta']['_gallery_quest_images'];
			} elseif ( isset( $json_params['meta']['_gallery_quest_images'] ) ) {
				$attachment_ids = $json_params['meta']['_gallery_quest_images'];
			}
			
			if ( null !== $attachment_ids ) {
				if ( ! is_array( $attachment_ids ) ) {
					$attachment_ids = array();
				}
				$sanitized = array_map( 'absint', $attachment_ids );
				$sanitized = array_values( array_filter( $sanitized ) );
				
				// Force save BEFORE WordPress processes it
				update_post_meta( $post_id, '_gallery_quest_images', $sanitized );
				
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'Gallery Quest: Saved meta via pre-dispatch: ' . print_r( $sanitized, true ) );
				}
			} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Gallery Quest: Could not find _gallery_quest_images in any request source' );
			}
		}
		
		return $result;
	}
	
	/**
	 * Intercept meta update at the lowest level.
	 *
	 * @param null|bool $check      Whether to allow updating metadata for the given type.
	 * @param int       $object_id  Object ID.
	 * @param string    $meta_key   Meta key.
	 * @param mixed     $meta_value Meta value.
	 * @param mixed     $prev_value Previous value.
	 * @return null|bool Modified check value.
	 */
	public function intercept_meta_update( $check, $object_id, $meta_key, $meta_value, $prev_value ): ?bool {
		if ( '_gallery_quest_images' === $meta_key ) {
			$post = get_post( $object_id );
			if ( $post && 'gallery_quest' === $post->post_type ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( "Gallery Quest: intercept_meta_update - Post ID: {$object_id}, Value: " . print_r( $meta_value, true ) );
				}
			}
		}
		return $check;
	}
	
	/**
	 * Save gallery images meta AFTER WordPress processes the save.
	 * This ensures we catch it even if WordPress didn't process it automatically.
	 *
	 * @param WP_Post         $post     Inserted or updated post object.
	 * @param WP_REST_Request $request  Request object.
	 * @param bool            $creating True when creating a post, false when updating.
	 */
	public function save_gallery_images_meta_after( $post, $request, $creating ): void {
		// ALWAYS log this - critical hook
		error_log( 'Gallery Quest: save_gallery_images_meta_after called for post ID: ' . $post->ID );
		
		// Get meta from request
		$meta = $request->get_param( 'meta' );
		
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Gallery Quest: Meta in after hook: ' . print_r( $meta, true ) );
		}
		
		if ( isset( $meta['_gallery_quest_images'] ) ) {
			$attachment_ids = $meta['_gallery_quest_images'];
			if ( ! is_array( $attachment_ids ) ) {
				$attachment_ids = array();
			}
			$sanitized = array_map( 'absint', $attachment_ids );
			$sanitized = array_values( array_filter( $sanitized ) );
			
			// Force save
			$result = update_post_meta( $post->ID, '_gallery_quest_images', $sanitized );
			
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Gallery Quest: Saved meta via after hook - Result: ' . ( $result ? 'true' : 'false' ) . ', Value: ' . print_r( $sanitized, true ) );
			}
		} else {
			// Check if meta was sent as empty array - clear it
			if ( $request->has_param( 'meta' ) && is_array( $meta ) && array_key_exists( '_gallery_quest_images', $meta ) ) {
				delete_post_meta( $post->ID, '_gallery_quest_images' );
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'Gallery Quest: Cleared meta via after hook (empty value)' );
				}
			}
		}
		
		// Verify it was saved
		$saved = get_post_meta( $post->ID, '_gallery_quest_images', true );
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Gallery Quest: Verified saved meta: ' . print_r( $saved, true ) );
		}
	}
	
	/**
	 * Force REST field into response.
	 *
	 * @param WP_REST_Response $response The response object.
	 * @param WP_Post          $post    Post object.
	 * @param WP_REST_Request  $request  Request object.
	 * @return WP_REST_Response Modified response.
	 */
	public function force_rest_field_in_response( $response, $post, $request ): WP_REST_Response {
		$saved_meta = get_post_meta( $post->ID, '_gallery_quest_images', true );
		if ( ! is_array( $saved_meta ) ) {
			$saved_meta = array();
		}
		
		$data = $response->get_data();
		
		// Force it into the response at top level
		$data['gallery_quest_images'] = $saved_meta;
		$data['_gallery_quest_images'] = $saved_meta;
		
		// Also ensure it's in meta
		if ( ! isset( $data['meta'] ) ) {
			$data['meta'] = array();
		}
		$data['meta']['_gallery_quest_images'] = $saved_meta;
		
		error_log( 'Gallery Quest: force_rest_field_in_response - Adding gallery_quest_images: ' . print_r( $saved_meta, true ) );
		
		$response->set_data( $data );
		
		return $response;
	}
	
	/**
	 * Ensure meta is included in REST API response.
	 *
	 * @param WP_REST_Response $response The response object.
	 * @param WP_Post          $post    Post object.
	 * @param WP_REST_Request  $request  Request object.
	 * @return WP_REST_Response Modified response.
	 */
	public function ensure_meta_in_response( $response, $post, $request ): WP_REST_Response {
		$saved_meta = get_post_meta( $post->ID, '_gallery_quest_images', true );
		if ( ! is_array( $saved_meta ) ) {
			$saved_meta = array();
		}
		
		$data = $response->get_data();
		if ( ! isset( $data['meta'] ) ) {
			$data['meta'] = array();
		}
		$data['meta']['_gallery_quest_images'] = $saved_meta;
		$response->set_data( $data );
		
		return $response;
	}
	
	/**
	 * Save meta directly - highest priority hook.
	 *
	 * @param object|WP_Post  $prepared_post Prepared post object or WP_Post.
	 * @param WP_REST_Request $request       Request object.
	 * @return object|WP_Post Modified post object.
	 */
	public function save_meta_directly( $prepared_post, $request ) {
		$meta = $request->get_param( 'meta' );
		error_log( 'Gallery Quest: save_meta_directly called - Meta: ' . print_r( $meta, true ) );
		
		if ( isset( $meta['_gallery_quest_images'] ) ) {
			$attachment_ids = $meta['_gallery_quest_images'];
			if ( ! is_array( $attachment_ids ) ) {
				$attachment_ids = array();
			}
			$sanitized = array_map( 'absint', $attachment_ids );
			$sanitized = array_values( array_filter( $sanitized ) );
			
			// Get post ID
			$post_id = null;
			if ( is_object( $prepared_post ) && isset( $prepared_post->ID ) ) {
				$post_id = $prepared_post->ID;
			} elseif ( is_array( $prepared_post ) && isset( $prepared_post['ID'] ) ) {
				$post_id = $prepared_post['ID'];
			} elseif ( $request->get_param( 'id' ) ) {
				$post_id = $request->get_param( 'id' );
			}
			
			if ( $post_id ) {
				update_post_meta( $post_id, '_gallery_quest_images', $sanitized );
				error_log( 'Gallery Quest: save_meta_directly saved meta for post ID: ' . $post_id . ', value: ' . print_r( $sanitized, true ) );
			} else {
				// Store for later if post doesn't exist yet
				$this->pending_meta_save = $sanitized;
				error_log( 'Gallery Quest: save_meta_directly stored pending meta: ' . print_r( $sanitized, true ) );
			}
		}
		
		return $prepared_post;
	}
	
	/**
	 * Force save meta before insert.
	 *
	 * @param object          $prepared_post Prepared post object.
	 * @param WP_REST_Request $request       Request object.
	 * @return object Modified post object.
	 */
	public function force_save_meta_before_insert( $prepared_post, $request ): object {
		$meta = $request->get_param( 'meta' );
		error_log( 'Gallery Quest: force_save_meta_before_insert - Meta: ' . print_r( $meta, true ) );
		
		if ( isset( $meta['_gallery_quest_images'] ) && isset( $prepared_post->ID ) ) {
			$attachment_ids = $meta['_gallery_quest_images'];
			if ( ! is_array( $attachment_ids ) ) {
				$attachment_ids = array();
			}
			$sanitized = array_map( 'absint', $attachment_ids );
			$sanitized = array_values( array_filter( $sanitized ) );
			
			// Store for later save (post doesn't exist yet)
			$this->pending_meta_save = $sanitized;
			error_log( 'Gallery Quest: Stored pending meta for insert: ' . print_r( $sanitized, true ) );
		}
		
		return $prepared_post;
	}
	
	/**
	 * Force save meta before update.
	 *
	 * @param object          $prepared_post Prepared post object.
	 * @param WP_REST_Request $request       Request object.
	 * @return object Modified post object.
	 */
	public function force_save_meta_before_update( $prepared_post, $request ): object {
		$meta = $request->get_param( 'meta' );
		error_log( 'Gallery Quest: force_save_meta_before_update - Post ID: ' . ( $prepared_post->ID ?? 'unknown' ) . ', Meta: ' . print_r( $meta, true ) );
		
		if ( isset( $meta['_gallery_quest_images'] ) && isset( $prepared_post->ID ) ) {
			$attachment_ids = $meta['_gallery_quest_images'];
			if ( ! is_array( $attachment_ids ) ) {
				$attachment_ids = array();
			}
			$sanitized = array_map( 'absint', $attachment_ids );
			$sanitized = array_values( array_filter( $sanitized ) );
			
			// Save immediately for updates
			update_post_meta( $prepared_post->ID, '_gallery_quest_images', $sanitized );
			error_log( 'Gallery Quest: Saved meta in force_save_meta_before_update: ' . print_r( $sanitized, true ) );
		}
		
		return $prepared_post;
	}
	
	/**
	 * Intercept REST API response.
	 *
	 * @param array           $result  Response data.
	 * @param WP_REST_Server  $server  Server instance.
	 * @param WP_REST_Request $request Request object.
	 * @return array Modified result.
	 */
	public function intercept_rest_response( $result, $server, $request ): array {
		$route = $request->get_route();
		
		if ( preg_match( '#/wp/v2/gallery-quest/(\d+)#', $route, $matches ) && in_array( $request->get_method(), array( 'POST', 'PUT', 'PATCH' ), true ) ) {
			$post_id = (int) $matches[1];
			$meta    = $request->get_param( 'meta' );
			
			error_log( "Gallery Quest: intercept_rest_response - Route: {$route}, Method: {$request->get_method()}, Post ID: {$post_id}" );
			error_log( 'Gallery Quest: Meta in response intercept: ' . print_r( $meta, true ) );
			
			if ( isset( $meta['_gallery_quest_images'] ) ) {
				$attachment_ids = $meta['_gallery_quest_images'];
				if ( ! is_array( $attachment_ids ) ) {
					$attachment_ids = array();
				}
				$sanitized = array_map( 'absint', $attachment_ids );
				$sanitized = array_values( array_filter( $sanitized ) );
				
				update_post_meta( $post_id, '_gallery_quest_images', $sanitized );
				error_log( 'Gallery Quest: Saved meta in intercept_rest_response: ' . print_r( $sanitized, true ) );
			}
			
			// Also check if we have pending meta from insert
			if ( isset( $this->pending_meta_save ) && isset( $result['id'] ) ) {
				update_post_meta( $result['id'], '_gallery_quest_images', $this->pending_meta_save );
				error_log( 'Gallery Quest: Saved pending meta from insert: ' . print_r( $this->pending_meta_save, true ) );
				unset( $this->pending_meta_save );
			}
		}
		
		return $result;
	}
	
	/**
	 * Log REST API response to see what was saved.
	 *
	 * @param WP_REST_Response $response The response object.
	 * @param WP_Post          $post    Post object.
	 * @param WP_REST_Request  $request  Request object.
	 * @return WP_REST_Response Modified response.
	 */
	public function log_rest_response( $response, $post, $request ): WP_REST_Response {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$saved_meta = get_post_meta( $post->ID, '_gallery_quest_images', true );
			$response_data = $response->get_data();
			error_log( "Gallery Quest: REST response - Post ID: {$post->ID}" );
			error_log( 'Gallery Quest: Saved meta from DB: ' . print_r( $saved_meta, true ) );
			error_log( 'Gallery Quest: Response meta: ' . print_r( $response_data['meta'] ?? 'not set', true ) );
		}
		return $response;
	}
	
	/**
	 * Save gallery images meta from save_post hook.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function save_gallery_images_meta_from_post( int $post_id, $post ): void {
		// Skip autosaves and revisions
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		
		// Check if this is a REST API request
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			// REST API requests are handled by other hooks
			return;
		}
		
		// For classic editor, check $_POST
		if ( isset( $_POST['meta']['_gallery_quest_images'] ) ) {
			$attachment_ids = array_map( 'absint', (array) $_POST['meta']['_gallery_quest_images'] );
			$sanitized      = array_values( array_filter( $attachment_ids ) );
			update_post_meta( $post_id, '_gallery_quest_images', $sanitized );
			
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Gallery Quest: Saved meta via save_post: ' . print_r( $sanitized, true ) );
			}
		}
	}
	
	/**
	 * Ensure meta is included in REST API request.
	 *
	 * @param object          $prepared_post Prepared post object.
	 * @param WP_REST_Request $request       Request object.
	 * @return object Modified post object.
	 */
	public function ensure_meta_in_request( $prepared_post, $request ): object {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Gallery Quest: ensure_meta_in_request called' );
		}
		
		$meta = $request->get_param( 'meta' );
		if ( ! is_array( $meta ) ) {
			$meta = array();
		}
		
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Gallery Quest: Meta in request: ' . print_r( $meta, true ) );
		}
		
		// If meta._gallery_quest_images is set, ensure it's properly formatted
		if ( isset( $meta['_gallery_quest_images'] ) ) {
			if ( ! is_array( $meta['_gallery_quest_images'] ) ) {
				$meta['_gallery_quest_images'] = array();
			}
			$request->set_param( 'meta', $meta );
		}
		
		return $prepared_post;
	}
	
	/**
	 * Save gallery images meta when post is saved via REST API.
	 *
	 * @param WP_Post         $post     Inserted or updated post object.
	 * @param WP_REST_Request $request  Request object.
	 * @param bool            $creating True when creating a post, false when updating.
	 */
	public function save_gallery_images_meta( $post, $request, $creating ): void {
		// Debug: Log that this function was called - ALWAYS log this
		error_log( 'Gallery Quest: save_gallery_images_meta called for post ID: ' . $post->ID . ', creating: ' . ( $creating ? 'true' : 'false' ) );
		
		// Try multiple ways to get the meta from the request
		$meta = $request->get_param( 'meta' );
		
		// Debug: Log the entire request (only in debug mode)
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Gallery Quest: REST API Request params: ' . print_r( $request->get_params(), true ) );
			error_log( 'Gallery Quest: Meta param: ' . print_r( $meta, true ) );
		}
		
		if ( isset( $meta['_gallery_quest_images'] ) ) {
			$attachment_ids = $meta['_gallery_quest_images'];
			
			// Sanitize the value
			if ( ! is_array( $attachment_ids ) ) {
				$attachment_ids = array();
			}
			
			$sanitized = array_map( 'absint', $attachment_ids );
			$sanitized = array_values( array_filter( $sanitized ) );
			
			// Save the meta
			$result = update_post_meta( $post->ID, '_gallery_quest_images', $sanitized );
			
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Gallery Quest: update_post_meta result: ' . ( $result ? 'true' : 'false' ) );
				error_log( 'Gallery Quest: Saved meta value: ' . print_r( $sanitized, true ) );
			}
		} else {
			// Check if meta was sent but the field is empty/null
			if ( $request->has_param( 'meta' ) && is_array( $meta ) && array_key_exists( '_gallery_quest_images', $meta ) ) {
				// Meta key exists but value is empty/null - clear it
				delete_post_meta( $post->ID, '_gallery_quest_images' );
				
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'Gallery Quest: Cleared meta (empty value received)' );
				}
			} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Gallery Quest: No _gallery_quest_images in meta. Meta keys: ' . print_r( array_keys( $meta ? $meta : array() ), true ) );
			}
		}
	}
	
	/**
	 * Maybe save gallery images meta - checks post type before saving.
	 *
	 * @param WP_Post         $post     Inserted or updated post object.
	 * @param WP_REST_Request $request  Request object.
	 * @param bool            $creating True when creating a post, false when updating.
	 */
	public function maybe_save_gallery_images_meta( $post, $request, $creating ): void {
		// Only process gallery_quest post type
		if ( 'gallery_quest' !== $post->post_type ) {
			return;
		}
		
		$this->save_gallery_images_meta( $post, $request, $creating );
	}
	
	/**
	 * Debug logging for meta save (only in debug mode).
	 *
	 * @param WP_Post         $post     Inserted or updated post object.
	 * @param WP_REST_Request $request  Request object.
	 * @param bool            $creating True when creating a post, false when updating.
	 */
	public function debug_log_meta_save( $post, $request, $creating ): void {
		$meta = $request->get_param( 'meta' );
		if ( isset( $meta['_gallery_quest_images'] ) ) {
			error_log( 'Gallery Quest: Meta received in REST API: ' . print_r( $meta['_gallery_quest_images'], true ) );
			$saved_meta = get_post_meta( $post->ID, '_gallery_quest_images', true );
			error_log( 'Gallery Quest: Meta saved to database: ' . print_r( $saved_meta, true ) );
		}
	}

	/**
	 * Register meta field for gallery images.
	 */
	public function register_meta_field(): void {
		// Ensure post type exists before registering meta
		if ( ! post_type_exists( 'gallery_quest' ) ) {
			return;
		}

		// Try BOTH methods: register_post_meta AND register_rest_field
		// register_post_meta for basic registration
		$result = register_post_meta(
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
				'update_callback'   => function( $value, $post ) {
					if ( ! is_array( $value ) ) {
						$value = array();
					}
					$sanitized = array_map( 'absint', $value );
					$sanitized = array_values( array_filter( $sanitized ) );
					
					update_post_meta( $post->ID, '_gallery_quest_images', $sanitized );
					
					return $sanitized;
				},
			)
		);
		
		// ALSO register as REST field for more control
		// Register for BOTH post type names (with and without hyphen)
		$rest_fields = array(
			'gallery_quest' => 'gallery_quest',
			'gallery-quest' => 'gallery-quest',
		);
		
		foreach ( $rest_fields as $post_type => $rest_base ) {
			register_rest_field(
				$rest_base,
				'gallery_quest_images', // Don't use underscore prefix for REST fields
				array(
					'get_callback'    => function( $post ) use ( $post_type ) {
						$post_id = is_array( $post ) ? $post['id'] : $post->ID;
						$meta = get_post_meta( $post_id, '_gallery_quest_images', true );
						if ( ! is_array( $meta ) ) {
							$meta = array();
						}
						return $meta;
					},
					'update_callback' => function( $value, $post ) use ( $post_type ) {
						$post_id = is_object( $post ) ? $post->ID : ( is_array( $post ) ? $post['id'] : $post );
						
						if ( ! is_array( $value ) ) {
							$value = array();
						}
						$sanitized = array_map( 'absint', $value );
						$sanitized = array_values( array_filter( $sanitized ) );
						
						update_post_meta( $post_id, '_gallery_quest_images', $sanitized );
						
						return true;
					},
					'schema'          => array(
						'description' => __( 'Gallery images attachment IDs', 'gallery-quest' ),
						'type'        => 'array',
						'items'       => array(
							'type' => 'integer',
						),
						'context'     => array( 'view', 'edit' ),
					),
				)
			);
		}
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

			wp_localize_script(
				'gallery-quest-meta-panel',
				'galleryQuestData',
				array(
					'restUrl' => esc_url_raw( rest_url( 'gallery-quest/v1/' ) ),
					'nonce'   => wp_create_nonce( 'wp_rest' ),
				)
			);
		}
	}

}

