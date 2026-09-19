<?php
/**
 * Tests for MCP server registration.
 *
 * @package McpServer
 */

declare( strict_types=1 );

namespace McpServer\Tests;

use McpServer\Server;
use WP\MCP\Core\McpAdapter;

final class ServerTest extends TestCase {

	public function test_server_exposes_every_component(): void {
		$server = McpAdapter::instance()->get_server( Server::ID );

		$this->assertNotNull( $server );
		$this->assertCount( 7, $server->get_tools() );
		$this->assertCount( 1, $server->get_resources() );
		$this->assertCount( 2, $server->get_prompts() );
	}
}
