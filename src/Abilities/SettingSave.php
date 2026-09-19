<?php
/**
 * Ability: update site settings.
 *
 * @package McpServer
 */

declare( strict_types=1 );

namespace McpServer\Abilities;

use McpServer\AllowedSettings;

/**
 * Updates allowlisted site settings.
 */
class SettingSave extends Ability {

	/**
	 * {@inheritDoc}
	 */
	protected function slug(): string {
		return 'setting-save';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function label(): string {
		return __( 'Save settings', 'mcp-server' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function description(): string {
		return sprintf(
			/* translators: %s: comma separated setting names */
			__( 'Update site settings: %s.', 'mcp-server' ),
			implode( ', ', AllowedSettings::names() )
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function input_schema(): array {
		return array(
			'type'       => 'object',
			'required'   => array( 'settings' ),
			'properties' => array(
				'settings' => array(
					'type'                 => 'object',
					'description'          => __( 'Setting values keyed by name.', 'mcp-server' ),
					'minProperties'        => 1,
					'additionalProperties' => array( 'type' => array( 'string', 'integer' ) ),
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
					'description'          => __( 'The saved values, read back from the database.', 'mcp-server' ),
					'additionalProperties' => true,
				),
			),
		);
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
		$error = AllowedSettings::reject( array_keys( $input['settings'] ) );

		// Checked before the loop so one bad name saves nothing.
		if ( $error ) {
			return $error;
		}

		$saved = array();
		foreach ( $input['settings'] as $name => $value ) {
			// Core's sanitize_option() runs inside update_option() and knows each option's rules.
			update_option( $name, is_string( $value ) ? sanitize_text_field( $value ) : $value );
			$saved[ $name ] = get_option( $name );
		}

		return array( 'settings' => $saved );
	}
}
