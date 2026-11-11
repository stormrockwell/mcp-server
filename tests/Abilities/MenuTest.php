<?php
/**
 * Tests for the menu abilities.
 *
 * @package McpServer
 */

declare( strict_types=1 );

namespace McpServer\Tests\Abilities;

use McpServer\Tests\TestCase;

final class MenuTest extends TestCase {

	public function test_editor_is_denied(): void {
		$this->act_as( 'editor' );

		$this->assertWPError( $this->run_ability( 'mcp-server/menu-search' ) );
		$this->assertWPError( $this->run_ability( 'mcp-server/menu-save', array( 'name' => 'Nope' ) ) );
	}

	public function test_creates_a_menu_with_items(): void {
		$this->act_as( 'administrator' );

		$result = $this->run_ability(
			'mcp-server/menu-save',
			array(
				'name'  => 'Footer',
				'items' => array(
					array(
						'title' => 'Home',
						'url'   => 'https://example.org/',
					),
					array(
						'title' => 'About',
						'url'   => 'https://example.org/about',
					),
				),
			)
		);

		$this->assertSame( 'Footer', $result['menu']['name'] );
		$this->assertSame( array( 'Home', 'About' ), wp_list_pluck( $result['menu']['items'], 'title' ) );
	}

	public function test_create_requires_a_name(): void {
		$this->act_as( 'administrator' );

		$result = $this->run_ability( 'mcp-server/menu-save', array( 'items' => array() ) );

		$this->assertWPError( $result );
		$this->assertSame( 'mcp_server_missing_name', $result->get_error_code() );
	}

	public function test_update_replaces_items_and_renames(): void {
		$this->act_as( 'administrator' );
		$menu_id = wp_create_nav_menu( 'Main' );
		wp_update_nav_menu_item(
			$menu_id,
			0,
			array(
				'menu-item-title'  => 'Old',
				'menu-item-url'    => 'https://example.org/old',
				'menu-item-type'   => 'custom',
				'menu-item-status' => 'publish',
			)
		);

		$result = $this->run_ability(
			'mcp-server/menu-save',
			array(
				'id'    => $menu_id,
				'name'  => 'Primary',
				'items' => array(
					array(
						'title' => 'New',
						'url'   => 'https://example.org/new',
					),
				),
			)
		);

		$this->assertSame( 'Primary', $result['menu']['name'] );
		$this->assertSame( array( 'New' ), wp_list_pluck( $result['menu']['items'], 'title' ) );
	}

	public function test_save_replaces_several_old_items_in_order(): void {
		$this->act_as( 'administrator' );
		$menu_id = wp_create_nav_menu( 'Main' );
		foreach ( array( 'Old A', 'Old B' ) as $title ) {
			wp_update_nav_menu_item(
				$menu_id,
				0,
				array(
					'menu-item-title'  => $title,
					'menu-item-url'    => 'https://example.org/old',
					'menu-item-type'   => 'custom',
					'menu-item-status' => 'publish',
				)
			);
		}
		$old_ids = wp_list_pluck( wp_get_nav_menu_items( $menu_id ), 'ID' );

		$result = $this->run_ability(
			'mcp-server/menu-save',
			array(
				'id'    => $menu_id,
				'items' => array(
					array(
						'title' => 'New A',
						'url'   => 'https://example.org/a',
					),
					array(
						'title' => 'New B',
						'url'   => 'https://example.org/b',
					),
					array(
						'title' => 'New C',
						'url'   => 'https://example.org/c',
					),
				),
			)
		);

		$this->assertSame( array( 'New A', 'New B', 'New C' ), wp_list_pluck( $result['menu']['items'], 'title' ) );
		$this->assertSame( array( 'New A', 'New B', 'New C' ), wp_list_pluck( wp_get_nav_menu_items( $menu_id ), 'title' ) );
		foreach ( $old_ids as $old_id ) {
			$this->assertNull( get_post( $old_id ) );
		}
	}

	public function test_failed_add_keeps_old_items_and_leaves_no_strays(): void {
		$this->act_as( 'administrator' );
		$menu_id = wp_create_nav_menu( 'Main' );
		foreach ( array( 'Old A', 'Old B' ) as $title ) {
			wp_update_nav_menu_item(
				$menu_id,
				0,
				array(
					'menu-item-title'  => $title,
					'menu-item-url'    => 'https://example.org/old',
					'menu-item-type'   => 'custom',
					'menu-item-status' => 'publish',
				)
			);
		}
		$old_ids = wp_list_pluck( wp_get_nav_menu_items( $menu_id ), 'ID' );

		$reject_bad = static function ( $maybe_empty, $postarr ) {
			return 'Bad' === ( $postarr['post_title'] ?? '' ) ? true : $maybe_empty;
		};
		add_filter( 'wp_insert_post_empty_content', $reject_bad, 10, 2 );

		$result = $this->run_ability(
			'mcp-server/menu-save',
			array(
				'id'    => $menu_id,
				'items' => array(
					array(
						'title' => 'Good',
						'url'   => 'https://example.org/good',
					),
					array(
						'title' => 'Bad',
						'url'   => 'https://example.org/bad',
					),
				),
			)
		);

		remove_filter( 'wp_insert_post_empty_content', $reject_bad, 10 );

		$this->assertWPError( $result );
		$remaining = wp_get_nav_menu_items( $menu_id );
		$this->assertSame( $old_ids, wp_list_pluck( $remaining, 'ID' ) );
		$this->assertSame( array( 'Old A', 'Old B' ), wp_list_pluck( $remaining, 'title' ) );
	}

	public function test_update_without_items_keeps_them(): void {
		$this->act_as( 'administrator' );
		$menu_id = wp_create_nav_menu( 'Main' );
		wp_update_nav_menu_item(
			$menu_id,
			0,
			array(
				'menu-item-title'  => 'Stay',
				'menu-item-url'    => 'https://example.org/stay',
				'menu-item-type'   => 'custom',
				'menu-item-status' => 'publish',
			)
		);

		$result = $this->run_ability(
			'mcp-server/menu-save',
			array(
				'id'   => $menu_id,
				'name' => 'Renamed',
			)
		);

		$this->assertSame( array( 'Stay' ), wp_list_pluck( $result['menu']['items'], 'title' ) );
	}

	public function test_unknown_menu_is_an_error(): void {
		$this->act_as( 'administrator' );

		$this->assertWPError(
			$this->run_ability(
				'mcp-server/menu-save',
				array(
					'id'   => 999999,
					'name' => 'Ghost',
				)
			)
		);
	}

	public function test_search_filters_by_name_and_id(): void {
		$this->act_as( 'administrator' );
		$footer = wp_create_nav_menu( 'Footer links' );
		$header = wp_create_nav_menu( 'Header' );

		$by_name = $this->run_ability( 'mcp-server/menu-search', array( 'search' => 'foot' ) );
		$by_id   = $this->run_ability( 'mcp-server/menu-search', array( 'id' => $header ) );
		$all     = $this->run_ability( 'mcp-server/menu-search' );

		$this->assertSame( array( $footer ), wp_list_pluck( $by_name['menus'], 'id' ) );
		$this->assertSame( array( $header ), wp_list_pluck( $by_id['menus'], 'id' ) );
		$this->assertCount( 2, $all['menus'] );
	}
}
