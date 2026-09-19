<?php
/**
 * MCP server registration.
 *
 * @package McpServer
 */

declare( strict_types=1 );

namespace McpServer;

use McpServer\Abilities\Ability;
use McpServer\Prompts\Prompt;
use WP\MCP\Core\McpAdapter;
use WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler;
use WP\MCP\Infrastructure\Observability\NullMcpObservabilityHandler;
use WP\MCP\Transport\HttpTransport;

/**
 * Tells the MCP Adapter which abilities and prompts this server exposes.
 */
class Server {

	// Clients name this ID in their config, for example `wp mcp-adapter serve --server=mcp-server`.
	const ID = 'mcp-server';

	/**
	 * Creates the server.
	 *
	 * @param McpAdapter $adapter   The adapter, passed by the mcp_adapter_init action.
	 * @param Ability[]  $abilities Abilities to expose.
	 * @param Prompt[]   $prompts   Prompts to expose.
	 */
	public function register( McpAdapter $adapter, array $abilities, array $prompts ): void {
		$tools     = array();
		$resources = array();

		foreach ( $abilities as $ability ) {
			if ( 'resource' === $ability->type() ) {
				$resources[] = $ability->name();
			} else {
				$tools[] = $ability->name();
			}
		}

		$built_prompts = array();
		foreach ( $prompts as $prompt ) {
			$built = $prompt->build();

			if ( is_wp_error( $built ) ) {
				_doing_it_wrong( __METHOD__, esc_html( $built->get_error_message() ), esc_html( MCP_SERVER_VERSION ) );
				continue;
			}

			$built_prompts[] = $built;
		}

		$result = $adapter->create_server(
			self::ID,
			'mcp-server/v1',
			'mcp',
			__( 'MCP Server', 'mcp-server' ),
			__( 'Posts, pages, patterns, menus, and settings for this WordPress site.', 'mcp-server' ),
			MCP_SERVER_VERSION,
			array( HttpTransport::class ),
			ErrorLogMcpErrorHandler::class,
			NullMcpObservabilityHandler::class,
			$tools,
			$resources,
			$built_prompts
		);

		if ( is_wp_error( $result ) ) {
			_doing_it_wrong( __METHOD__, esc_html( $result->get_error_message() ), esc_html( MCP_SERVER_VERSION ) );
		}
	}
}
