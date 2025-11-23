<?php
/**
 * Server-side rendering for Gallery Quest block.
 *
 * @package GalleryQuest
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block default content.
 * @var WP_Block $block     Block instance.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$gallery_quest_gallery_id   = isset( $attributes['galleryId'] ) ? absint( $attributes['galleryId'] ) : 0;
$gallery_quest_item_count   = isset( $attributes['itemCount'] ) ? absint( $attributes['itemCount'] ) : 20;
$gallery_quest_columns      = isset( $attributes['columns'] ) ? absint( $attributes['columns'] ) : 3;
$gallery_quest_show_filters = isset( $attributes['showFilters'] ) ? (bool) $attributes['showFilters'] : true;
$gallery_quest_filter_logic = isset( $attributes['filterLogic'] ) ? sanitize_key( $attributes['filterLogic'] ) : 'OR';

// Validate filter logic.
if ( ! in_array( $gallery_quest_filter_logic, array( 'AND', 'OR' ), true ) ) {
	$gallery_quest_filter_logic = 'OR';
}

// Validate columns.
$gallery_quest_columns = max( 1, min( 6, $gallery_quest_columns ) );

// Generate unique ID for this block instance.
$gallery_quest_unique_id = uniqid( 'gallery-quest-' );

// Get block wrapper attributes.
$gallery_quest_wrapper_attributes = get_block_wrapper_attributes(
	array(
		'id'               => $gallery_quest_unique_id,
		'class'            => "gallery-quest-block gallery-quest-columns-{$gallery_quest_columns}",
		'data-gallery-id'  => $gallery_quest_gallery_id,
		'data-filter-logic' => $gallery_quest_filter_logic,
		'data-show-filters' => $gallery_quest_show_filters ? 'true' : 'false',
	)
);

// If no gallery selected, show message.
if ( ! $gallery_quest_gallery_id || $gallery_quest_gallery_id === 0 ) {
	?>
	<div <?php echo $gallery_quest_wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<p class="gallery-quest-message">
			<?php esc_html_e( 'Please select a gallery in the block settings.', 'gallery-quest' ); ?>
		</p>
	</div>
	<?php
	return;
}

// Get gallery post.
$gallery_quest_gallery = get_post( $gallery_quest_gallery_id );
if ( ! $gallery_quest_gallery || 'gallery_quest' !== $gallery_quest_gallery->post_type ) {
	?>
	<div <?php echo $gallery_quest_wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<p class="gallery-quest-message">
			<?php esc_html_e( 'Gallery not found.', 'gallery-quest' ); ?>
		</p>
	</div>
	<?php
	return;
}

// Get attachment IDs from post meta.
$gallery_quest_attachment_ids = get_post_meta( $gallery_quest_gallery_id, '_gallery_quest_images', true );
if ( ! is_array( $gallery_quest_attachment_ids ) || empty( $gallery_quest_attachment_ids ) ) {
	?>
	<div <?php echo $gallery_quest_wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<p class="gallery-quest-message">
			<?php esc_html_e( 'No images in this gallery.', 'gallery-quest' ); ?>
		</p>
	</div>
	<?php
	return;
}

// Sanitize attachment IDs.
$gallery_quest_attachment_ids = array_map( 'absint', $gallery_quest_attachment_ids );
$gallery_quest_attachment_ids = array_filter( $gallery_quest_attachment_ids );

if ( empty( $gallery_quest_attachment_ids ) ) {
	?>
	<div <?php echo $gallery_quest_wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<p class="gallery-quest-message">
			<?php esc_html_e( 'No valid images in this gallery.', 'gallery-quest' ); ?>
		</p>
	</div>
	<?php
	return;
}

// Query attachments.
$gallery_quest_query_args = array(
	'post_type'      => 'attachment',
	'post_status'    => 'inherit',
	'post__in'       => $gallery_quest_attachment_ids,
	'posts_per_page' => -1, // Get all to sort properly.
	'orderby'        => 'post__in',
	'order'          => 'ASC',
	'no_found_rows'  => false,
	'update_post_meta_cache' => true,
	'update_post_term_cache' => true,
);

$gallery_quest_query = new WP_Query( $gallery_quest_query_args );

// Apply item count limit.
if ( $gallery_quest_query->have_posts() ) {
	$gallery_quest_posts = $gallery_quest_query->posts;
	$gallery_quest_posts = array_slice( $gallery_quest_posts, 0, $gallery_quest_item_count );
	$gallery_quest_query->posts = $gallery_quest_posts;
	$gallery_quest_query->post_count = count( $gallery_quest_posts );
}

// Get unique taxonomy terms from attachments for filter UI.
$gallery_quest_available_terms = array();
if ( $gallery_quest_show_filters && $gallery_quest_query->have_posts() ) {
	$gallery_quest_taxonomies = array(
		'gallery_character' => __( 'Characters', 'gallery-quest' ),
		'gallery_artist'    => __( 'Artists', 'gallery-quest' ),
		'gallery_rarity'    => __( 'Rarity', 'gallery-quest' ),
	);

	foreach ( $gallery_quest_taxonomies as $gallery_quest_taxonomy => $gallery_quest_label ) {
		$gallery_quest_terms = wp_get_object_terms( $gallery_quest_attachment_ids, $gallery_quest_taxonomy, array( 'fields' => 'all' ) );
		if ( ! is_wp_error( $gallery_quest_terms ) && ! empty( $gallery_quest_terms ) ) {
			$gallery_quest_available_terms[ $gallery_quest_taxonomy ] = array(
				'label' => $gallery_quest_label,
				'terms' => $gallery_quest_terms,
			);
		}
	}
}

?>
<div <?php echo $gallery_quest_wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php if ( $gallery_quest_show_filters && ! empty( $gallery_quest_available_terms ) ) : ?>
		<div class="gallery-quest-filters" role="region" aria-label="<?php esc_attr_e( 'Gallery filters', 'gallery-quest' ); ?>">
			<?php foreach ( $gallery_quest_available_terms as $gallery_quest_taxonomy => $gallery_quest_data ) : ?>
				<fieldset class="gallery-quest-filter-group">
					<legend><?php echo esc_html( $gallery_quest_data['label'] ); ?></legend>
					<div class="gallery-quest-filter-dropdown-wrapper">
						<select
							class="gallery-quest-filter-select"
							data-taxonomy="<?php echo esc_attr( $gallery_quest_taxonomy ); ?>"
							aria-label="<?php echo esc_attr( sprintf( __( 'Filter by %s', 'gallery-quest' ), $gallery_quest_data['label'] ) ); ?>"
						>
							<option value=""><?php echo esc_html( sprintf( __( 'All %s', 'gallery-quest' ), $gallery_quest_data['label'] ) ); ?></option>
							<?php foreach ( $gallery_quest_data['terms'] as $gallery_quest_term ) : ?>
								<option value="<?php echo esc_attr( $gallery_quest_term->slug ); ?>">
									<?php echo esc_html( $gallery_quest_term->name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
				</fieldset>
			<?php endforeach; ?>

			<div class="gallery-quest-filter-actions">
				<button type="button" class="gallery-quest-clear-filters">
					<?php esc_html_e( 'Clear All Filters', 'gallery-quest' ); ?>
				</button>
			</div>
		</div>
	<?php endif; ?>

	<div class="gallery-quest-grid" style="--gallery-columns: <?php echo esc_attr( $gallery_quest_columns ); ?>;">
		<?php
		if ( $gallery_quest_query->have_posts() ) {
			while ( $gallery_quest_query->have_posts() ) {
				$gallery_quest_query->the_post();
				$gallery_quest_attachment_id = get_the_ID();
				$gallery_quest_image_url     = wp_get_attachment_image_url( $gallery_quest_attachment_id, 'gallery-medium' );
				$gallery_quest_full_url      = wp_get_attachment_image_url( $gallery_quest_attachment_id, 'full' );
				$gallery_quest_image_meta    = wp_get_attachment_metadata( $gallery_quest_attachment_id );
				$gallery_quest_width         = isset( $gallery_quest_image_meta['width'] ) ? $gallery_quest_image_meta['width'] : 0;
				$gallery_quest_height        = isset( $gallery_quest_image_meta['height'] ) ? $gallery_quest_image_meta['height'] : 0;
				$gallery_quest_alt_text      = get_post_meta( $gallery_quest_attachment_id, '_wp_attachment_image_alt', true );
				$gallery_quest_title         = get_the_title( $gallery_quest_attachment_id );

				// Get taxonomy terms for data attributes.
				$gallery_quest_character_terms = wp_get_object_terms( $gallery_quest_attachment_id, 'gallery_character', array( 'fields' => 'slugs' ) );
				$gallery_quest_artist_terms    = wp_get_object_terms( $gallery_quest_attachment_id, 'gallery_artist', array( 'fields' => 'slugs' ) );
				$gallery_quest_rarity_terms     = wp_get_object_terms( $gallery_quest_attachment_id, 'gallery_rarity', array( 'fields' => 'slugs' ) );

				$gallery_quest_data_attributes = array(
					'data-item-id' => $gallery_quest_attachment_id,
					'data-character' => ! is_wp_error( $gallery_quest_character_terms ) ? esc_attr( implode( ',', $gallery_quest_character_terms ) ) : '',
					'data-artist' => ! is_wp_error( $gallery_quest_artist_terms ) ? esc_attr( implode( ',', $gallery_quest_artist_terms ) ) : '',
					'data-rarity' => ! is_wp_error( $gallery_quest_rarity_terms ) ? esc_attr( implode( ',', $gallery_quest_rarity_terms ) ) : '',
				);

				$gallery_quest_data_attr_string = implode(
					' ',
					array_map(
						function( $key, $value ) {
							return $key . '="' . $value . '"';
						},
						array_keys( $gallery_quest_data_attributes ),
						$gallery_quest_data_attributes
					)
				);
				?>
				<div class="gallery-quest-item" <?php echo $gallery_quest_data_attr_string; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
					<a
						href="<?php echo esc_url( $gallery_quest_full_url ); ?>"
						class="gallery-quest-item-link"
						data-pswp-width="<?php echo esc_attr( $gallery_quest_width ); ?>"
						data-pswp-height="<?php echo esc_attr( $gallery_quest_height ); ?>"
						target="_blank"
					>
						<?php
						echo wp_get_attachment_image(
							$gallery_quest_attachment_id,
							'gallery-medium',
							false,
							array(
								'loading' => 'lazy',
								'alt'     => $gallery_quest_alt_text ? esc_attr( $gallery_quest_alt_text ) : esc_attr( $gallery_quest_title ),
							)
						);
						?>
					</a>
				</div>
				<?php
			}
			wp_reset_postdata();
		}
		?>
	</div>
</div>
