<?php
/**
 * Tests for the post-search ability.
 *
 * @package McpServer
 */

declare( strict_types=1 );

namespace McpServer\Tests\Abilities;

use McpServer\Tests\TestCase;

final class PostSearchTest extends TestCase {

	public function test_logged_out_user_is_denied(): void {
		wp_set_current_user( 0 );

		$this->assertWPError( $this->run_ability( 'mcp-server/post-search' ) );
	}

	public function test_subscriber_is_denied(): void {
		$this->act_as( 'subscriber' );

		$this->assertWPError( $this->run_ability( 'mcp-server/post-search' ) );
	}

	public function test_pages_need_the_page_capability(): void {
		self::factory()->post->create( array( 'post_type' => 'page' ) );

		$this->act_as( 'author' );
		$as_author = $this->run_ability( 'mcp-server/post-search', array( 'post_type' => 'page' ) );

		$this->act_as( 'editor' );
		$as_editor = $this->run_ability( 'mcp-server/post-search', array( 'post_type' => 'page' ) );

		$this->assertWPError( $as_author );
		$this->assertCount( 1, $as_editor['posts'] );
	}

	public function test_is_annotated_as_readonly(): void {
		$meta = wp_get_ability( 'mcp-server/post-search' )->get_meta();

		$this->assertTrue( $meta['annotations']['readonly'] );
	}

	public function test_finds_published_posts_by_search_term(): void {
		$this->act_as( 'contributor' );
		$match = self::factory()->post->create( array( 'post_title' => 'Hello Pineapple' ) );
		self::factory()->post->create( array( 'post_title' => 'Unrelated' ) );

		$result = $this->run_ability( 'mcp-server/post-search', array( 'search' => 'Pineapple' ) );

		$this->assertSame( array( $match ), wp_list_pluck( $result['posts'], 'id' ) );
		$this->assertSame( 'Hello Pineapple', $result['posts'][0]['title'] );
	}

	public function test_id_returns_a_list_of_one(): void {
		$this->act_as( 'contributor' );
		$id = self::factory()->post->create();
		self::factory()->post->create();

		$result = $this->run_ability( 'mcp-server/post-search', array( 'id' => $id ) );

		$this->assertSame( array( $id ), wp_list_pluck( $result['posts'], 'id' ) );
	}

	public function test_drafts_by_other_users_are_dropped(): void {
		$other = self::factory()->user->create( array( 'role' => 'author' ) );
		$draft = self::factory()->post->create(
			array(
				'post_status' => 'draft',
				'post_author' => $other,
			)
		);

		$this->act_as( 'author' );
		$as_author = $this->run_ability( 'mcp-server/post-search', array( 'status' => 'draft' ) );

		$this->act_as( 'editor' );
		$as_editor = $this->run_ability( 'mcp-server/post-search', array( 'status' => 'draft' ) );

		$this->assertSame( array(), $as_author['posts'] );
		$this->assertSame( array( $draft ), wp_list_pluck( $as_editor['posts'], 'id' ) );
	}

	public function test_unknown_post_type_is_an_error(): void {
		$this->act_as( 'contributor' );

		$result = $this->run_ability( 'mcp-server/post-search', array( 'post_type' => 'nope' ) );

		$this->assertWPError( $result );
		$this->assertSame( 'mcp_server_invalid_post_type', $result->get_error_code() );
	}
}
