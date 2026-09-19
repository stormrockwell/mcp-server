<?php
/**
 * The settings the MCP server may read and write.
 *
 * @package McpServer
 */

declare( strict_types=1 );

namespace McpServer;

use WP_Error;

/**
 * Guards the options table.
 *
 * Without a list, a model could rewrite siteurl or active_plugins and take the site down.
 */
class AllowedSettings {

	/**
	 * Option names the setting abilities may touch.
	 *
	 * @return string[]
	 */
	public static function names(): array {
		/**
		 * Filters the option names exposed over MCP.
		 *
		 * @param string[] $names Option names.
		 */
		return (array) apply_filters(
			'mcp_server_allowed_settings',
			array( 'blogname', 'blogdescription', 'timezone_string', 'date_format', 'time_format', 'posts_per_page' )
		);
	}

	/**
	 * Returns an error naming the allowed settings when any name is off the list.
	 *
	 * @param string[] $names Requested option names.
	 */
	public static function reject( array $names ): ?WP_Error {
		$blocked = array_diff( $names, self::names() );

		if ( ! $blocked ) {
			return null;
		}

		return new WP_Error(
			'mcp_server_setting_not_allowed',
			sprintf(
				/* translators: 1: rejected setting names, 2: allowed setting names */
				__( 'Not allowed: %1$s. Allowed settings are: %2$s.', 'mcp-server' ),
				implode( ', ', $blocked ),
				implode( ', ', self::names() )
			)
		);
	}
}
