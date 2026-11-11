<?php
/**
 * WordPress test configuration.
 *
 * Points wp-phpunit at the Studio WordPress install and a throwaway SQLite
 * file, so tests never touch the live site's database.
 *
 * @package McpServer
 */

// Core is four levels up from wp-content/plugins/mcp-server. A checkout anywhere else, such as a worktree, names it in WP_CORE_DIR.
define( 'ABSPATH', rtrim( getenv( 'WP_CORE_DIR' ) ?: dirname( __DIR__, 4 ), '/' ) . '/' );

define( 'DB_ENGINE', 'sqlite' );
// The bootstrap resolves WP_TESTS_DB_DIR, so parallel runs can each keep their own database file.
define( 'DB_DIR', getenv( 'WP_TESTS_DB_DIR' ) );
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
