<?php
/**
 * Harness smoke test.
 *
 * @package McpServer
 */

declare( strict_types=1 );

namespace McpServer\Tests;

use McpServer\Plugin;

final class HarnessTest extends TestCase {

	public function test_plugin_class_loads(): void {
		$this->assertTrue( class_exists( Plugin::class ) );
	}

	public function test_adapter_is_active(): void {
		$this->assertTrue( class_exists( \WP\MCP\Core\McpAdapter::class ) );
	}

	public function test_category_is_registered(): void {
		$this->assertNotNull( wp_get_ability_category( Plugin::CATEGORY ) );
	}
}
