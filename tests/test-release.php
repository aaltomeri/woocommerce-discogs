<?php
/**
 * Class ReleaseTest
 *
 * @package Woocommerce_Discogs
 */


use WC_Discogs\Admin\Settings;
use WC_Discogs\API\Discogs\Database;
use WC_Discogs\Release;
use WC_Discogs\Media;

/**
 * Release Class test case.
 */
class ReleaseTest extends WP_UnitTestCase {

	static $__NAMESPACE__ = 'WC_Discogs';

	/**
	 * testing if we can accurately tell of a Release has artwork or not
	 */
	function test_get_artists() {

		$taxonomy = self::$__NAMESPACE__ . '_artist';
		$separator = " | ";

		$post_id = $this->factory->post->create();
		$this->assertNotNull($post_id);

		$artists_terms = [];
		$artists_terms[0] = 'Nick Drake';
		wp_set_object_terms( $post_id, $artists_terms, $taxonomy , false );

		$release = new Release( $post_id );
		$this->assertEquals( $artists_terms[0], $release->get_artists() );

		$artists_terms[1] = 'The books';
		wp_set_object_terms( $post_id, $artists_terms, $taxonomy , false );

		$this->assertEquals( implode($separator, $artists_terms), $release->get_artists( $separator ) );

	}

	function test_get_has_associated_post() {

		$post_id = $this->factory->post->create();
		$this->assertNotNull($post_id);

		$release = new Release( $post_id );
		$this->assertNotNull($release->post->ID);

	}

	function test_set_artwork()  {

		// we need to setup a few settings like the default place holder uri
		new Settings();

		$release = $this->_create_release();

		// test that we don't want to proceed if Release already has had an artwork set
		// relying on has_artwork to tell us what to do
		$filename = ( DATA_DIR . '/images/test-artwork.jpg' );
		$contents = file_get_contents($filename);
		$upload = wp_upload_bits(basename($filename), null, $contents);
		$this->assertTrue( empty($upload['error']) );

		$attachment_id = $this->_make_attachment($upload, $release->post->ID);
		$this->assertNotNull($attachment_id);
		set_post_thumbnail($release->post->ID, $attachment_id);
		$this->assertEquals($attachment_id, $release->has_artwork());

		$already_set_attachment_id = $release->set_artwork();
		$image_infos = wp_get_attachment_image_src($attachment_id, null);
		$this->assertEquals( 1, preg_match("/test-artwork/", $image_infos[0]) );
		$this->assertEquals( $attachment_id, $already_set_attachment_id );


		// TODO
		// test product/post has parent w/ correct title

		// test not fetching artwork if an image named "Artist + Title"
		// exists in the Media Library
		$attachment_id = $this->_make_attachment( $upload );
		$attachment_title = $release->get_artists() . ' - ' . $release->post->post_title;
		wp_update_post( [ 'ID' => $attachment_id, 'post_title' => $attachment_title ]);
		$attachment = get_post( $attachment_id );
		$this->assertEquals( $attachment_title, $attachment->post_title );
		$this->assertEquals( $attachment_id, $release->set_artwork() );
		$post_thumbnail_id = get_post_thumbnail_id( $release->post->ID );
		$this->assertEquals( $attachment_id, $post_thumbnail_id );


		// test using the default placeholder image
		// first pass : it does not exist in Media Library
		$release = $this->_create_release( "Some Unknown Artist", "Some Unknown Title");
		$first_attachment_id = $release->set_artwork();
		$default_image_path_parts = explode('/', Media::$default_artwork_image_uri);
		$default_image_filename = $default_image_path_parts[ count($default_image_path_parts) - 1];
		$default_image_basename = explode('.', $default_image_filename)[0];
		$attachment_url = wp_get_attachment_url( $first_attachment_id );
		$this->assertEquals(
			1,
			preg_match("/$default_image_basename/", $attachment_url )
		);

		// second pass : it has been created before and we want to re-use it
		$release = $this->_create_release( "Some Other Unknown Artist", "Some Other Unknown Title");
		$second_attachment_id = $release->set_artwork();
		$this->assertEquals( $first_attachment_id, $second_attachment_id);

		// test correct artwork has been attached to Release
		$release = $this->_create_release( '16 Horsepower', 'Hoarse' );
		$attachment_id = $release->set_artwork();
		$attachment_url = wp_get_attachment_image_url( $attachment_id );
		$this->assertEquals(
			1,
			preg_match("/R-1823745-1245810570/", $attachment_url)
		);

	}

	function test_has_artwork() {

		// does not have artwork
		$post_id = $this->factory->post->create();
		$release = new Release( $post_id );
		$this->assertFalse($release->has_artwork());

		// has artwork
		$filename = ( DATA_DIR . '/images/test-artwork.jpg' );
		$contents = file_get_contents($filename);
		$upload = wp_upload_bits(basename($filename), null, $contents);
		$this->assertTrue( empty($upload['error']) );

		$post_id = $this->factory->post->create();
		$release = new Release( $post_id );
		$attachment_id = $this->_make_attachment($upload, $post_id);
		$this->assertNotNull($attachment_id);
		set_post_thumbnail($post_id, $attachment_id);
		$this->assertEquals($attachment_id, $release->has_artwork());

		// has featured image but it is the default placeholder
		$post_id = $this->factory->post->create();
		$release = new Release( $post_id );
		$attachment_id = Media::attach_from_url(Media::$default_artwork_image_uri, $post_id);
		$this->assertTrue(is_int($attachment_id));
		$image_infos = wp_get_attachment_image_src($attachment_id, null);
		$image_src = $image_infos[0];
		$default_image_path_parts = explode('/', Media::$default_artwork_image_uri);
		$default_image_filename = $default_image_path_parts[ count($default_image_path_parts) - 1];
		$default_image_basename = explode('.', $default_image_filename)[0];
		$this->assertEquals(
			1,
			preg_match(
				"/$default_image_basename/",
				$image_src
			)
		);
		$this->assertFalse($release->has_artwork());

	}


	/**
	* HELPERS
	**/
	function _create_release( $artist_name = 'Nick Drake', $release_title = 'Five Leaves Left') {

		$taxonomy = self::$__NAMESPACE__ . '_artist';

		$post_id = $this->factory->post->create();
		$this->assertNotNull($post_id);

		$artists_terms = [];
		$artists_terms[0] = $artist_name;
		wp_set_object_terms( $post_id, $artists_terms, $taxonomy , false );

		// set title
		wp_update_post(	['ID' => $post_id, 'post_title' => $release_title ] );

		$release = new Release( $post_id );
		$this->assertEquals($release->post->post_title, $release_title );

		return $release;
	}

}
