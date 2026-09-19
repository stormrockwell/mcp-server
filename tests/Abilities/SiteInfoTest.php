<?php
/**
 * Tests for the site-info resource.
 *
 * @package McpServer
 */

declare( strict_types=1 );

namespace McpServer\Tests\Abilities;

use McpServer\Abilities\SiteInfo;
use McpServer\Tests\TestCase;

final class SiteInfoTest extends TestCase {

	public function test_is_a_resource_with_a_uri(): void {
		$meta = wp_get_ability( 'mcp-server/site-info' )->get_meta();

		$this->assertSame( 'resource', ( new SiteInfo() )->type() );
		$this->assertSame( 'resource', $meta['mcp']['type'] );
		$this->assertSame( 'site://info', $meta['mcp']['uri'] );
	}

	public function test_does_not_set_the_deprecated_top_level_readonly_annotation(): void {
		$meta = wp_get_ability( 'mcp-server/site-info' )->get_meta();

		// Core always fills meta.annotations with null defaults, so the key itself is
		// always present. A resource must not set "readonly" there itself: the adapter
		// treats any non-null value in that top level location as deprecated and warns,
		// and a resource is read only by nature anyway.
		$this->assertNull( $meta['annotations']['readonly'] );
	}

	public function test_logged_out_user_is_denied(): void {
		wp_set_current_user( 0 );

		$this->assertWPError( $this->run_ability( 'mcp-server/site-info' ) );
	}

	public function test_subscriber_is_denied(): void {
		$this->act_as( 'subscriber' );

		$this->assertWPError( $this->run_ability( 'mcp-server/site-info' ) );
	}

	public function test_returns_site_details(): void {
		$this->act_as( 'contributor' );
		update_option( 'blogname', 'Info Site' );

		$result = $this->run_ability( 'mcp-server/site-info' );

		$this->assertSame( 'Info Site', $result['name'] );
		$this->assertSame( home_url(), $result['url'] );
		$this->assertSame( get_bloginfo( 'version' ), $result['wp_version'] );
	}
}
