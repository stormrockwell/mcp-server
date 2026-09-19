<?php
/**
 * Tests for the setting abilities.
 *
 * @package McpServer
 */

declare( strict_types=1 );

namespace McpServer\Tests\Abilities;

use McpServer\Tests\TestCase;

final class SettingTest extends TestCase {

	public function test_editor_is_denied(): void {
		$this->act_as( 'editor' );

		$this->assertWPError( $this->run_ability( 'mcp-server/setting-get' ) );
		$this->assertWPError( $this->run_ability( 'mcp-server/setting-save', array( 'settings' => array( 'blogname' => 'X' ) ) ) );
	}

	public function test_get_returns_every_allowed_setting_by_default(): void {
		$this->act_as( 'administrator' );
		update_option( 'blogname', 'Test Site' );

		$result = $this->run_ability( 'mcp-server/setting-get' );

		$this->assertSame( 'Test Site', $result['settings']['blogname'] );
		$this->assertArrayHasKey( 'posts_per_page', $result['settings'] );
		$this->assertArrayNotHasKey( 'siteurl', $result['settings'] );
	}

	public function test_get_returns_only_the_requested_names(): void {
		$this->act_as( 'administrator' );

		$result = $this->run_ability( 'mcp-server/setting-get', array( 'names' => array( 'blogname' ) ) );

		$this->assertSame( array( 'blogname' ), array_keys( $result['settings'] ) );
	}

	public function test_get_rejects_names_outside_the_allowlist(): void {
		$this->act_as( 'administrator' );

		$result = $this->run_ability( 'mcp-server/setting-get', array( 'names' => array( 'siteurl' ) ) );

		$this->assertWPError( $result );
		$this->assertSame( 'mcp_server_setting_not_allowed', $result->get_error_code() );
	}

	public function test_save_updates_allowed_settings(): void {
		$this->act_as( 'administrator' );

		$result = $this->run_ability(
			'mcp-server/setting-save',
			array(
				'settings' => array(
					'blogname'       => 'Renamed',
					'posts_per_page' => 7,
				),
			)
		);

		$this->assertSame( 'Renamed', get_option( 'blogname' ) );
		$this->assertSame( 7, (int) get_option( 'posts_per_page' ) );
		$this->assertSame( 'Renamed', $result['settings']['blogname'] );
	}

	public function test_save_rejects_names_outside_the_allowlist_and_saves_nothing(): void {
		$this->act_as( 'administrator' );
		update_option( 'blogname', 'Before' );
		$siteurl = get_option( 'siteurl' );

		$result = $this->run_ability(
			'mcp-server/setting-save',
			array(
				'settings' => array(
					'blogname' => 'After',
					'siteurl'  => 'https://evil.example',
				),
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'mcp_server_setting_not_allowed', $result->get_error_code() );
		$this->assertSame( 'Before', get_option( 'blogname' ) );
		$this->assertSame( $siteurl, get_option( 'siteurl' ) );
	}

	public function test_allowlist_is_filterable(): void {
		$this->act_as( 'administrator' );
		add_filter( 'mcp_server_allowed_settings', static fn( array $names ) => array_merge( $names, array( 'start_of_week' ) ) );

		$result = $this->run_ability( 'mcp-server/setting-get', array( 'names' => array( 'start_of_week' ) ) );

		$this->assertArrayHasKey( 'start_of_week', $result['settings'] );
	}
}
