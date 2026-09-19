<?php
/**
 * Plugin Name:       MCP Server
 * Description:       A starter MCP server for WordPress. Exposes abilities as MCP tools, resources, and prompts.
 * Version:           0.1.0
 * Requires at least: 6.9
 * Requires PHP:      8.2
 * Requires Plugins:  mcp-adapter
 * Author:            Storm Rockwell
 * License:           GPL-2.0-or-later
 * Text Domain:       mcp-server
 *
 * @package McpServer
 */

declare( strict_types=1 );

namespace McpServer;

defined( 'ABSPATH' ) || exit;

define( 'MCP_SERVER_VERSION', '0.1.0' );

// A git checkout has no vendor folder until `composer install` runs.
if ( ! is_readable( __DIR__ . '/vendor/autoload.php' ) ) {
	add_action(
		'admin_notices',
		static function () {
			wp_admin_notice(
				__( 'MCP Server is missing its autoloader. Run "composer install" in the plugin folder, or install the release zip.', 'mcp-server' ),
				array( 'type' => 'error' )
			);
		}
	);
	return;
}

require_once __DIR__ . '/vendor/autoload.php';

( new Plugin() )->init();
