<?php
/**
 * PHPUnit bootstrap.
 *
 * @package McpServer
 */

declare( strict_types=1 );

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

putenv( 'WP_PHPUNIT__TESTS_CONFIG=' . __DIR__ . '/wp-tests-config.php' );

$mcp_server_db_dir = rtrim( getenv( 'WP_TESTS_DB_DIR' ) ?: sys_get_temp_dir() . '/mcp-server-wp-tests', '/' ) . '/';
putenv( 'WP_TESTS_DB_DIR=' . $mcp_server_db_dir );
if ( ! is_dir( $mcp_server_db_dir ) ) {
	mkdir( $mcp_server_db_dir, 0755, true );
}

// The adapter must be active too, or no MCP server is ever created.
$GLOBALS['wp_tests_options'] = array(
	'active_plugins' => array( 'mcp-adapter/mcp-adapter.php' ),
);

require_once dirname( __DIR__ ) . '/vendor/wp-phpunit/wp-phpunit/includes/functions.php';

// The plugin loads from this checkout rather than from wp-content/plugins, so a worktree tests its own code.
tests_add_filter(
	'muplugins_loaded',
	static function (): void {
		require dirname( __DIR__ ) . '/mcp-server.php';
	}
);

require_once dirname( __DIR__ ) . '/vendor/wp-phpunit/wp-phpunit/includes/bootstrap.php';

/**
 * The MCP Adapter only creates servers while "rest_api_init" is firing, which a WP-CLI
 * request reaches via "init" but a plain PHPUnit run never reaches on its own. Firing it
 * once here lets tests see the servers this plugin registers.
 */
do_action( 'rest_api_init' );
