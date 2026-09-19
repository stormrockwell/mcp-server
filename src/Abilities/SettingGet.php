<?php
/**
 * Ability: read site settings.
 *
 * @package McpServer
 */

declare( strict_types=1 );

namespace McpServer\Abilities;

use McpServer\AllowedSettings;

/**
 * Reads allowlisted site settings.
 */
class SettingGet extends Ability {

	/**
	 * {@inheritDoc}
	 */
	protected function slug(): string {
		return 'setting-get';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function label(): string {
		return __( 'Get settings', 'mcp-server' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function description(): string {
		return sprintf(
			/* translators: %s: comma separated setting names */
			__( 'Read site settings: %s.', 'mcp-server' ),
			implode( ', ', AllowedSettings::names() )
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function input_schema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'names' => array(
					'type'        => 'array',
					'items'       => array( 'type' => 'string' ),
					'description' => __( 'Setting names to read. Leave out to read them all.', 'mcp-server' ),
				),
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function output_schema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'settings' => array(
					'type'                 => 'object',
					'description'          => __( 'Setting values keyed by name.', 'mcp-server' ),
					'additionalProperties' => true,
				),
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function readonly(): bool {
		return true;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $input Validated input.
	 */
	public function permission( array $input ) {
		return current_user_can( 'manage_options' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $input Validated input.
	 */
	public function execute( array $input ) {
		$names = $input['names'] ?? AllowedSettings::names();
		$error = AllowedSettings::reject( $names );

		if ( $error ) {
			return $error;
		}

		$settings = array();
		foreach ( $names as $name ) {
			$settings[ $name ] = get_option( $name );
		}

		return array( 'settings' => $settings );
	}
}
