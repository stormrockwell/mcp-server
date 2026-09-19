<?php
/**
 * Tests for shared output shaping.
 *
 * @package McpServer
 */

declare( strict_types=1 );

namespace McpServer\Tests;

use McpServer\Format;

final class FormatTest extends TestCase {

	public function test_post_returns_exactly_the_expected_keys(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Hello Pineapple',
				'post_content' => 'Some content here.',
				'post_status'  => 'publish',
				'post_type'    => 'post',
			)
		);
		$post    = get_post( $post_id );

		$result = Format::post( $post );

		$this->assertSame(
			array( 'id', 'title', 'status', 'type', 'link', 'excerpt' ),
			array_keys( $result )
		);
		$this->assertSame( $post_id, $result['id'] );
		$this->assertSame( 'Hello Pineapple', $result['title'] );
		$this->assertSame( 'publish', $result['status'] );
		$this->assertSame( 'post', $result['type'] );
		$this->assertSame( get_permalink( $post ), $result['link'] );
		$this->assertSame( 'Some content here.', $result['excerpt'] );
	}

	public function test_title_is_the_raw_post_title_not_the_display_title(): void {
		// An administrator has unfiltered_html, so the title is stored exactly as given.
		$this->act_as( 'administrator' );

		$title   = 'It\'s "here" & now';
		$post_id = self::factory()->post->create(
			array(
				'post_title'  => $title,
				'post_status' => 'private',
			)
		);

		$result = Format::post( get_post( $post_id ) );

		$this->assertSame( $title, $result['title'] );
	}

	public function test_excerpt_is_empty_when_the_post_is_password_protected(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_content'  => 'Secret content here.',
				'post_password' => 'hunter2',
			)
		);

		$result = Format::post( get_post( $post_id ) );

		$this->assertSame( '', $result['excerpt'] );
	}

	public function test_excerpt_is_trimmed_to_thirty_words(): void {
		$words   = implode( ' ', array_fill( 0, 40, 'word' ) );
		$post_id = self::factory()->post->create(
			array(
				'post_content' => $words,
			)
		);

		$result = Format::post( get_post( $post_id ) );

		$this->assertSame( wp_trim_words( $words, 30 ), $result['excerpt'] );
		$this->assertNotSame( $words, $result['excerpt'] );
	}

	public function test_menu_returns_exactly_the_expected_shape(): void {
		$menu_id = wp_create_nav_menu( 'Footer' );
		wp_update_nav_menu_item(
			$menu_id,
			0,
			array(
				'menu-item-title'  => 'Home',
				'menu-item-url'    => 'https://example.org/',
				'menu-item-type'   => 'custom',
				'menu-item-status' => 'publish',
			)
		);
		$menu = wp_get_nav_menu_object( $menu_id );

		$result = Format::menu( $menu );

		$this->assertSame( array( 'id', 'name', 'items' ), array_keys( $result ) );
		$this->assertSame( $menu_id, $result['id'] );
		$this->assertSame( 'Footer', $result['name'] );
		$this->assertCount( 1, $result['items'] );
		$this->assertSame( array( 'id', 'title', 'url' ), array_keys( $result['items'][0] ) );
		$this->assertSame( 'Home', $result['items'][0]['title'] );
		$this->assertSame( 'https://example.org/', $result['items'][0]['url'] );
	}
}
