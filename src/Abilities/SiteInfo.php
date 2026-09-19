<?php
/**
 * Ability: site information, exposed as an MCP resource.
 *
 * @package McpServer
 */

declare( strict_types=1 );

namespace McpServer\Abilities;

/**
 * Read-only context about the site.
 *
 * A resource is context the client app attaches, where a tool is an action the model chooses.
 */
class SiteInfo extends Ability {

	/**
	 * {@inheritDoc}
	 */
	protected function slug(): string {
		return 'site-info';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function label(): string {
		return __( 'Site info', 'mcp-server' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function description(): string {
		return __( 'Name, tagline, URL, WordPress version, language, and timezone of this site.', 'mcp-server' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function input_schema(): array {
		return array();
	}

	/**
	 * {@inheritDoc}
	 */
	protected function output_schema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'name'        => array( 'type' => 'string' ),
				'description' => array( 'type' => 'string' ),
				'url'         => array( 'type' => 'string' ),
				'wp_version'  => array( 'type' => 'string' ),
				'language'    => array( 'type' => 'string' ),
				'timezone'    => array( 'type' => 'string' ),
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function type(): string {
		return 'resource';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function mcp_meta(): array {
		return array(
			'uri'      => 'site://info',
			'mimeType' => 'application/json',
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $input Validated input.
	 */
	public function permission( array $input ) {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $input Validated input.
	 */
	public function execute( array $input ) {
		return array(
			'name'        => get_bloginfo( 'name' ),
			'description' => get_bloginfo( 'description' ),
			'url'         => home_url(),
			'wp_version'  => get_bloginfo( 'version' ),
			'language'    => get_bloginfo( 'language' ),
			'timezone'    => wp_timezone_string(),
		);
	}
}
