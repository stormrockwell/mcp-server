<?php
/**
 * Ability: create or update a navigation menu.
 *
 * @package McpServer
 */

declare( strict_types=1 );

namespace McpServer\Abilities;

use McpServer\Format;
use WP_Error;

/**
 * Creates a menu, or updates one when an ID is given.
 */
class MenuSave extends Ability {

	/**
	 * {@inheritDoc}
	 */
	protected function slug(): string {
		return 'menu-save';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function label(): string {
		return __( 'Save menu', 'mcp-server' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function description(): string {
		return __( 'Create or update a navigation menu of custom links.', 'mcp-server' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function input_schema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'id'    => array(
					'type'        => 'integer',
					'description' => __( 'Menu to update. Leave out to create.', 'mcp-server' ),
				),
				'name'  => array(
					'type'        => 'string',
					'description' => __( 'Required when creating.', 'mcp-server' ),
				),
				'items' => array(
					'type'        => 'array',
					'description' => __( 'The full list of links, in order. Replaces the current items.', 'mcp-server' ),
					'items'       => array(
						'type'       => 'object',
						'required'   => array( 'title', 'url' ),
						'properties' => array(
							'title' => array( 'type' => 'string' ),
							'url'   => array( 'type' => 'string' ),
						),
					),
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
				'menu' => array(
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
		);
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
		$menu_id = (int) ( $input['id'] ?? 0 );

		if ( $menu_id && ! wp_get_nav_menu_object( $menu_id ) ) {
			return new WP_Error(
				'mcp_server_menu_not_found',
				/* translators: %d: menu ID */
				sprintf( __( 'No menu has the ID %d. Use menu-search to find the right one.', 'mcp-server' ), $menu_id )
			);
		}

		if ( ! $menu_id && empty( $input['name'] ) ) {
			return new WP_Error(
				'mcp_server_missing_name',
				__( '"name" is required when creating a menu.', 'mcp-server' )
			);
		}

		if ( isset( $input['name'] ) ) {
			// With an ID of 0 this creates the menu.
			$menu_id = wp_update_nav_menu_object( $menu_id, array( 'menu-name' => sanitize_text_field( $input['name'] ) ) );

			if ( is_wp_error( $menu_id ) ) {
				return $menu_id;
			}
		}

		if ( isset( $input['items'] ) ) {
			$replaced = $this->replace_items( $menu_id, $input['items'] );

			if ( is_wp_error( $replaced ) ) {
				return $replaced;
			}
		}

		return array( 'menu' => Format::menu( wp_get_nav_menu_object( $menu_id ) ) );
	}

	/**
	 * Swaps the menu's items for the given links.
	 *
	 * New items go in first and the old ones are deleted only once every add
	 * succeeded. A failed add removes the items this call created and keeps the old ones.
	 *
	 * @param int                                        $menu_id The menu.
	 * @param array<int, array{title:string,url:string}> $items   Links in display order.
	 * @return true|WP_Error True when the items were replaced, the failing add's error otherwise.
	 */
	private function replace_items( int $menu_id, array $items ) {
		$old_ids = array();
		$new_ids = array();
		foreach ( (array) wp_get_nav_menu_items( $menu_id ) as $old_item ) {
			$old_ids[] = $old_item->ID;
		}

		foreach ( array_values( $items ) as $index => $item ) {
			$added = wp_update_nav_menu_item(
				$menu_id,
				0,
				array(
					'menu-item-title'    => sanitize_text_field( $item['title'] ),
					'menu-item-url'      => esc_url_raw( $item['url'] ),
					'menu-item-type'     => 'custom',
					'menu-item-status'   => 'publish',
					'menu-item-position' => $index + 1,
				)
			);

			if ( is_wp_error( $added ) ) {
				foreach ( $new_ids as $new_id ) {
					wp_delete_post( $new_id, true );
				}

				return $added;
			}

			$new_ids[] = $added;
		}

		foreach ( $old_ids as $old_id ) {
			wp_delete_post( $old_id, true );
		}

		return true;
	}
}
