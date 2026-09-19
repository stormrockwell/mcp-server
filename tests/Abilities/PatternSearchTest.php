<?php
/**
 * Tests for the pattern-search ability.
 *
 * @package McpServer
 */

declare( strict_types=1 );

namespace McpServer\Tests\Abilities;

use McpServer\Tests\TestCase;

final class PatternSearchTest extends TestCase {

	private const CONTENT = '<!-- wp:paragraph --><p>Pineapple intro</p><!-- /wp:paragraph -->';

	public function set_up(): void {
		parent::set_up();

		register_block_pattern(
			'mcp-server-test/pineapple',
			array(
				'title'       => 'Pineapple article',
				'description' => 'An intro and three sections.',
				'categories'  => array( 'posts' ),
				'content'     => self::CONTENT,
			)
		);
		register_block_pattern(
			'mcp-server-test/footer',
			array(
				'title'      => 'Plain footer',
				'categories' => array( 'footer' ),
				'content'    => '<!-- wp:paragraph --><p>Footer</p><!-- /wp:paragraph -->',
			)
		);
	}

	public function tear_down(): void {
		unregister_block_pattern( 'mcp-server-test/pineapple' );
		unregister_block_pattern( 'mcp-server-test/footer' );

		parent::tear_down();
	}

	public function test_subscriber_is_denied(): void {
		$this->act_as( 'subscriber' );

		$this->assertWPError( $this->run_ability( 'mcp-server/pattern-search' ) );
	}

	public function test_is_annotated_as_readonly(): void {
		$meta = wp_get_ability( 'mcp-server/pattern-search' )->get_meta();

		$this->assertTrue( $meta['annotations']['readonly'] );
	}

	public function test_finds_patterns_by_search_term(): void {
		$this->act_as( 'contributor' );

		$result = $this->run_ability( 'mcp-server/pattern-search', array( 'search' => 'pineapple' ) );

		$this->assertSame( array( 'mcp-server-test/pineapple' ), wp_list_pluck( $result['patterns'], 'name' ) );
		$this->assertSame( 'Pineapple article', $result['patterns'][0]['title'] );
		$this->assertSame( array( 'posts' ), $result['patterns'][0]['categories'] );
	}

	public function test_filters_by_category(): void {
		$this->act_as( 'contributor' );

		$result = $this->run_ability( 'mcp-server/pattern-search', array( 'category' => 'footer' ) );
		$names  = wp_list_pluck( $result['patterns'], 'name' );

		$this->assertContains( 'mcp-server-test/footer', $names );
		$this->assertNotContains( 'mcp-server-test/pineapple', $names );
	}

	public function test_a_list_leaves_the_markup_out(): void {
		$this->act_as( 'contributor' );

		$result = $this->run_ability( 'mcp-server/pattern-search', array( 'search' => 'pineapple' ) );

		$this->assertArrayNotHasKey( 'content', $result['patterns'][0] );
	}

	public function test_name_returns_one_pattern_with_its_slots(): void {
		$this->act_as( 'contributor' );

		$result = $this->run_ability( 'mcp-server/pattern-search', array( 'name' => 'mcp-server-test/pineapple' ) );

		$this->assertCount( 1, $result['patterns'] );
		$this->assertSame(
			array(
				array(
					'index'     => 0,
					'block'     => 'core/paragraph',
					'attribute' => 'content',
					'value'     => 'Pineapple intro',
				),
			),
			$result['patterns'][0]['slots']
		);
		$this->assertArrayNotHasKey( 'content', $result['patterns'][0] );
	}

	public function test_markup_adds_the_block_markup(): void {
		$this->act_as( 'contributor' );

		$result = $this->run_ability(
			'mcp-server/pattern-search',
			array(
				'name'   => 'mcp-server-test/pineapple',
				'markup' => true,
			)
		);

		$this->assertSame( self::CONTENT, $result['patterns'][0]['content'] );
	}

	public function test_unknown_name_explains_the_fix(): void {
		$this->act_as( 'contributor' );

		$result = $this->run_ability( 'mcp-server/pattern-search', array( 'name' => 'mcp-server-test/missing' ) );

		$this->assertWPError( $result );
		$this->assertSame( 'mcp_server_pattern_not_found', $result->get_error_code() );
	}
}
