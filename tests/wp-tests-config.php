<?php
/**
 * WordPress test configuration.
 *
 * Points wp-phpunit at the Studio WordPress install and a throwaway SQLite
 * file, so tests never touch the live site's database.
 *
 * @package McpServer
 */

// Plugin is at wp-content/plugins/mcp-server, so core is four levels up.
define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );

define( 'DB_ENGINE', 'sqlite' );
define( 'DB_DIR', sys_get_temp_dir() . '/mcp-server-wp-tests/' );
define( 'DB_FILE', '.ht.sqlite' );

// The SQLite driver ignores these, but core expects them to be defined.
define( 'DB_NAME', 'mcp_server_tests' );
define( 'DB_USER', '' );
define( 'DB_PASSWORD', '' );
define( 'DB_HOST', '' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

define( 'WP_TESTS_DOMAIN', 'tests.local' );
define( 'WP_TESTS_EMAIL', 'admin@tests.local' );
define( 'WP_TESTS_TITLE', 'MCP Server Tests' );
define( 'WP_PHP_BINARY', PHP_BINARY );
define( 'WP_DEBUG', true );

$table_prefix = 'wptests_';
