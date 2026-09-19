<?php
/**
 * Tests for ability and prompt discovery.
 *
 * @package McpServer
 */

declare( strict_types=1 );

namespace McpServer\Tests;

use McpServer\Abilities\Ability;
use McpServer\Plugin;
use McpServer\Prompts\Prompt;

final class DiscoveryTest extends TestCase {

	public function test_every_discovered_ability_is_concrete(): void {
		$abilities = ( new Plugin() )->abilities();

		$this->assertNotEmpty( $abilities );
		foreach ( $abilities as $ability ) {
			$this->assertInstanceOf( Ability::class, $ability );
		}
	}

	public function test_discovers_post_search(): void {
		$names = array_map( static fn( Ability $ability ) => $ability->name(), ( new Plugin() )->abilities() );

		$this->assertContains( 'mcp-server/post-search', $names );
	}

	public function test_discovers_exactly_the_shipped_abilities(): void {
		$names = array_map( static fn( Ability $ability ) => $ability->name(), ( new Plugin() )->abilities() );
		sort( $names );

		$this->assertSame(
			array(
				'mcp-server/menu-save',
				'mcp-server/menu-search',
				'mcp-server/pattern-search',
				'mcp-server/post-save',
				'mcp-server/post-search',
				'mcp-server/setting-get',
				'mcp-server/setting-save',
				'mcp-server/site-info',
			),
			$names
		);
	}

	public function test_discovers_prompts_and_skips_the_base_class(): void {
		$prompts = ( new Plugin() )->prompts();

		$this->assertCount( 2, $prompts );
		$this->assertInstanceOf( Prompt::class, $prompts[0] );
	}
}
