<?php
/**
 * Ability: search navigation menus.
 *
 * @package McpServer
 */

declare( strict_types=1 );

namespace McpServer\Abilities;

use McpServer\Format;

/**
 * Lists navigation menus and their items.
 */
class MenuSearch extends Ability {

	/**
	 * {@inheritDoc}
	 */
	public function name(): string {
		return 'mcp-server/menu-search';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function label(): string {
		return __( 'Search menus', 'mcp-server' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function description(): string {
		return __( 'List navigation menus with their items.', 'mcp-server' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function input_schema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'id'     => array(
					'type'        => 'integer',
					'description' => 'Return only the menu with this ID.',
				),
				'search' => array(
					'type'        => 'string',
					'description' => 'Text to look for in the menu name.',
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
				'menus' => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'id'    => array( 'type' => 'integer' ),
							'name'  => array( 'type' => 'string' ),
							'items' => array(
								'type'  => 'array',
								'items' => array(
									'type'       => 'object',
									'properties' => array(
										'id'    => array( 'type' => 'integer' ),
										'title' => array( 'type' => 'string' ),
										'url'   => array( 'type' => 'string' ),
									),
								),
							),
						),
					),
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
		return current_user_can( 'edit_theme_options' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $input Validated input.
	 */
	public function execute( array $input ) {
		$menus = array();

		foreach ( wp_get_nav_menus() as $menu ) {
			if ( isset( $input['id'] ) && $menu->term_id !== (int) $input['id'] ) {
				continue;
			}
			if ( isset( $input['search'] ) && false === stripos( $menu->name, $input['search'] ) ) {
				continue;
			}
			$menus[] = Format::menu( $menu );
		}

		return array( 'menus' => $menus );
	}
}
