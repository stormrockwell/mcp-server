<?php
/**
 * Tests for the post-save ability.
 *
 * @package McpServer
 */

declare( strict_types=1 );

namespace McpServer\Tests\Abilities;

use McpServer\Tests\TestCase;

final class PostSaveTest extends TestCase {

	public function test_subscriber_cannot_create(): void {
		$this->act_as( 'subscriber' );

		$this->assertWPError( $this->run_ability( 'mcp-server/post-save', array( 'title' => 'Nope' ) ) );
	}

	public function test_creates_a_draft_by_default(): void {
		$this->act_as( 'author' );

		$result = $this->run_ability(
			'mcp-server/post-save',
			array(
				'title'   => 'Fresh',
				'content' => 'Body',
			)
		);

		$this->assertSame( 'Fresh', $result['post']['title'] );
		$this->assertSame( 'draft', $result['post']['status'] );
		$this->assertArrayHasKey( 'excerpt', $result['post'] );
		$this->assertSame( 'Body', get_post( $result['post']['id'] )->post_content );
	}

	public function test_create_requires_a_title(): void {
		$this->act_as( 'author' );

		$result = $this->run_ability( 'mcp-server/post-save', array( 'content' => 'Body' ) );

		$this->assertWPError( $result );
		$this->assertSame( 'mcp_server_missing_title', $result->get_error_code() );
	}

	public function test_updates_only_the_given_fields(): void {
		$author = $this->act_as( 'author' );
		$id     = self::factory()->post->create(
			array(
				'post_author'  => $author,
				'post_title'   => 'Old',
				'post_content' => 'Keep me',
			)
		);

		$result = $this->run_ability(
			'mcp-server/post-save',
			array(
				'id'    => $id,
				'title' => 'New',
			)
		);

		$this->assertSame( $id, $result['post']['id'] );
		$this->assertSame( 'New', get_post( $id )->post_title );
		$this->assertSame( 'Keep me', get_post( $id )->post_content );
	}

	public function test_author_cannot_update_another_authors_post(): void {
		$other = self::factory()->user->create( array( 'role' => 'author' ) );
		$id    = self::factory()->post->create( array( 'post_author' => $other ) );
		$this->act_as( 'author' );

		$result = $this->run_ability(
			'mcp-server/post-save',
			array(
				'id'    => $id,
				'title' => 'Hijack',
			)
		);

		$this->assertWPError( $result );
		$this->assertNotSame( 'Hijack', get_post( $id )->post_title );
	}

	public function test_editor_can_update_another_authors_post(): void {
		$other = self::factory()->user->create( array( 'role' => 'author' ) );
		$id    = self::factory()->post->create( array( 'post_author' => $other ) );
		$this->act_as( 'editor' );

		$result = $this->run_ability(
			'mcp-server/post-save',
			array(
				'id'    => $id,
				'title' => 'Edited',
			)
		);

		$this->assertSame( 'Edited', $result['post']['title'] );
	}

	public function test_contributor_cannot_publish(): void {
		$this->act_as( 'contributor' );

		$result = $this->run_ability(
			'mcp-server/post-save',
			array(
				'title'  => 'Live?',
				'status' => 'publish',
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'mcp_server_cannot_publish', $result->get_error_code() );
	}

	public function test_contributor_cannot_set_private(): void {
		$this->act_as( 'contributor' );

		$result = $this->run_ability(
			'mcp-server/post-save',
			array(
				'title'  => 'Sneaky',
				'status' => 'private',
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'mcp_server_cannot_publish', $result->get_error_code() );
		$this->assertEmpty( get_posts( array( 'title' => 'Sneaky', 'post_status' => 'any' ) ) );
	}

	public function test_author_cannot_create_pages(): void {
		$this->act_as( 'author' );

		$result = $this->run_ability(
			'mcp-server/post-save',
			array(
				'title'     => 'A page',
				'post_type' => 'page',
			)
		);

		$this->assertWPError( $result );
	}

	public function test_missing_post_is_an_error(): void {
		$this->act_as( 'administrator' );

		$result = $this->run_ability(
			'mcp-server/post-save',
			array(
				'id'    => 999999,
				'title' => 'Ghost',
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'mcp_server_post_not_found', $result->get_error_code() );
	}

	public function test_subscriber_gets_no_detail_about_a_missing_post(): void {
		$this->act_as( 'subscriber' );

		$result = $this->run_ability( 'mcp-server/post-save', array( 'id' => 999999 ) );

		$this->assertWPError( $result );
		$this->assertNotSame( 'mcp_server_post_not_found', $result->get_error_code() );
	}

	/**
	 * Registers the pattern the sections tests fill, and removes it again after the test.
	 */
	private function register_intro_pattern(): void {
		register_block_pattern(
			'mcp-server-test/intro',
			array(
				'title'   => 'Intro',
				'content' => '<!-- wp:heading --><h2 class="wp-block-heading">Old heading</h2><!-- /wp:heading --><!-- wp:paragraph --><p>Old text</p><!-- /wp:paragraph -->',
			)
		);
	}

	public function tear_down(): void {
		if ( \WP_Block_Patterns_Registry::get_instance()->is_registered( 'mcp-server-test/intro' ) ) {
			unregister_block_pattern( 'mcp-server-test/intro' );
		}

		parent::tear_down();
	}

	public function test_sections_fill_patterns_into_the_content(): void {
		$this->act_as( 'author' );
		$this->register_intro_pattern();

		$result = $this->run_ability(
			'mcp-server/post-save',
			array(
				'title'    => 'Cats',
				'sections' => array(
					array(
						'pattern' => 'mcp-server-test/intro',
						'slots'   => array( 'Bengals', 'Spotted cats.' ),
					),
					array(
						'pattern' => 'mcp-server-test/intro',
						'slots'   => array( null, 'Second section.' ),
					),
				),
			)
		);

		$this->assertSame(
			'<!-- wp:heading --><h2 class="wp-block-heading">Bengals</h2><!-- /wp:heading --><!-- wp:paragraph --><p>Spotted cats.</p><!-- /wp:paragraph -->'
			. "\n\n"
			. '<!-- wp:heading --><h2 class="wp-block-heading">Old heading</h2><!-- /wp:heading --><!-- wp:paragraph --><p>Second section.</p><!-- /wp:paragraph -->',
			get_post( $result['post']['id'] )->post_content
		);
	}

	public function test_sections_replace_the_content_of_an_existing_post(): void {
		$author = $this->act_as( 'author' );
		$this->register_intro_pattern();
		$id = self::factory()->post->create(
			array(
				'post_author'  => $author,
				'post_content' => 'Before',
			)
		);

		$this->run_ability(
			'mcp-server/post-save',
			array(
				'id'       => $id,
				'sections' => array( array( 'pattern' => 'mcp-server-test/intro' ) ),
			)
		);

		$this->assertStringContainsString( 'Old heading', get_post( $id )->post_content );
		$this->assertStringNotContainsString( 'Before', get_post( $id )->post_content );
	}

	public function test_sections_and_content_together_is_an_error(): void {
		$this->act_as( 'author' );
		$this->register_intro_pattern();

		$result = $this->run_ability(
			'mcp-server/post-save',
			array(
				'title'    => 'Both',
				'content'  => 'Body',
				'sections' => array( array( 'pattern' => 'mcp-server-test/intro' ) ),
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'mcp_server_content_conflict', $result->get_error_code() );
	}

	public function test_a_bad_section_saves_nothing_and_names_the_section(): void {
		$this->act_as( 'author' );
		$this->register_intro_pattern();
		$before = wp_count_posts()->draft;

		$unknown = $this->run_ability(
			'mcp-server/post-save',
			array(
				'title'    => 'Nope',
				'sections' => array( array( 'pattern' => 'mcp-server-test/missing' ) ),
			)
		);
		$too_many = $this->run_ability(
			'mcp-server/post-save',
			array(
				'title'    => 'Nope',
				'sections' => array(
					array( 'pattern' => 'mcp-server-test/intro' ),
					array(
						'pattern' => 'mcp-server-test/intro',
						'slots'   => array( 'a', 'b', 'c' ),
					),
				),
			)
		);

		$this->assertSame( 'mcp_server_pattern_not_found', $unknown->get_error_code() );
		$this->assertSame( 'mcp_server_too_many_slots', $too_many->get_error_code() );
		$this->assertStringContainsString( 'Section 2', $too_many->get_error_message() );
		$this->assertSame( $before, wp_count_posts()->draft );
	}
}
