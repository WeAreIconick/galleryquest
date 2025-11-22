<?php
/**
 * PHPUnit tests for Gallery Quest Taxonomies
 *
 * @package GalleryQuest
 */

use PHPUnit\Framework\TestCase;

/**
 * Test class for Gallery_Quest_Taxonomies
 */
class TestTaxonomies extends TestCase {
	/**
	 * Test taxonomy registration
	 */
	public function test_taxonomies_registered() {
		$taxonomies = get_taxonomies( array( 'object_type' => array( 'attachment' ) ) );
		
		$this->assertArrayHasKey( 'gallery_character', $taxonomies );
		$this->assertArrayHasKey( 'gallery_artist', $taxonomies );
		$this->assertArrayHasKey( 'gallery_rarity', $taxonomies );
	}

	/**
	 * Test taxonomy REST API availability
	 */
	public function test_taxonomies_rest_api_enabled() {
		$character_tax = get_taxonomy( 'gallery_character' );
		
		$this->assertTrue( $character_tax->show_in_rest );
		$this->assertEquals( 'gallery_character', $character_tax->rest_base );
	}

	/**
	 * Test meta field registration
	 */
	public function test_sort_order_meta_registered() {
		$meta = get_registered_meta_keys( 'attachment', '_gallery_quest_sort_order' );
		
		$this->assertNotEmpty( $meta );
		$this->assertEquals( 'integer', $meta['_gallery_quest_sort_order']['type'] );
		$this->assertTrue( $meta['_gallery_quest_sort_order']['show_in_rest'] );
	}
}


