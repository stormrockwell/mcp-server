<?php
/**
 * Tests for the draft-page prompt.
 *
 * @package McpServer
 */

declare( strict_types=1 );

namespace McpServer\Tests\Prompts;

use McpServer\Prompts\DraftPage;
use McpServer\Tests\TestCase;
use WP\MCP\Domain\Prompts\McpPrompt;

final class DraftPageTest extends TestCase {

	public function test_builds_a_native_mcp_prompt(): void {
		$this->assertInstanceOf( McpPrompt::class, ( new DraftPage() )->build() );
	}

	public function test_message_carries_the_topic_and_tone(): void {
		$result = ( new DraftPage() )->messages(
			array(
				'topic' => 'our bakery',
				'tone'  => 'warm',
			)
		);
		$text   = $result['messages'][0]['content']['text'];

		$this->assertSame( 'user', $result['messages'][0]['role'] );
		$this->assertStringContainsString( 'our bakery', $text );
		$this->assertStringContainsString( 'warm', $text );
	}

	public function test_saves_as_a_page_built_from_sections(): void {
		$text = ( new DraftPage() )->messages( array( 'topic' => 'our bakery' ) )['messages'][0]['content']['text'];

		$this->assertStringContainsString( 'post-save', $text );
		$this->assertStringContainsString( '"post_type" set to "page"', $text );
		$this->assertStringContainsString( '"sections"', $text );
		$this->assertStringContainsString( 'draft', $text );
	}

	public function test_no_patterns_means_the_model_picks_them(): void {
		$text = ( new DraftPage() )->messages( array( 'topic' => 'our bakery' ) )['messages'][0]['content']['text'];

		$this->assertStringContainsString( 'content patterns with pattern-search', $text );
	}

	public function test_patterns_are_used_in_the_order_given(): void {
		$result = ( new DraftPage() )->messages(
			array(
				'topic'    => 'our bakery',
				'patterns' => 'my-theme/hero, my-theme/faq',
			)
		);
		$text   = $result['messages'][0]['content']['text'];

		$this->assertStringContainsString( 'my-theme/hero, my-theme/faq', $text );
		$this->assertStringContainsString( 'in order', $text );
		$this->assertStringNotContainsString( 'content patterns', $text );
	}

	public function test_tone_has_a_default(): void {
		$result = ( new DraftPage() )->messages( array( 'topic' => 'our bakery' ) );

		$this->assertStringContainsString( 'friendly', $result['messages'][0]['content']['text'] );
	}

	public function test_permission_follows_the_page_post_type(): void {
		$prompt = ( new DraftPage() )->build();

		$this->act_as( 'author' );
		$this->assertFalse( $prompt->check_permission( array() ) );

		$this->act_as( 'editor' );
		$this->assertTrue( $prompt->check_permission( array() ) );
	}
}
