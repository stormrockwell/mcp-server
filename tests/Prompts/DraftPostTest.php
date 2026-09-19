<?php
/**
 * Tests for the draft-post prompt.
 *
 * @package McpServer
 */

declare( strict_types=1 );

namespace McpServer\Tests\Prompts;

use McpServer\Prompts\DraftPost;
use McpServer\Tests\TestCase;
use WP\MCP\Domain\Prompts\McpPrompt;

final class DraftPostTest extends TestCase {

	public function test_builds_a_native_mcp_prompt(): void {
		$this->assertInstanceOf( McpPrompt::class, ( new DraftPost() )->build() );
	}

	public function test_message_carries_the_topic_and_tone(): void {
		$result = ( new DraftPost() )->messages(
			array(
				'topic' => 'composting',
				'tone'  => 'playful',
			)
		);
		$text   = $result['messages'][0]['content']['text'];

		$this->assertSame( 'user', $result['messages'][0]['role'] );
		$this->assertStringContainsString( 'composting', $text );
		$this->assertStringContainsString( 'playful', $text );
		$this->assertStringContainsString( 'post-save', $text );
	}

	public function test_pattern_tells_the_model_to_fill_it(): void {
		$result = ( new DraftPost() )->messages(
			array(
				'topic'   => 'composting',
				'pattern' => 'my-theme/article',
			)
		);
		$text   = $result['messages'][0]['content']['text'];

		$this->assertStringContainsString( 'pattern-search', $text );
		$this->assertStringContainsString( 'my-theme/article', $text );
		$this->assertStringContainsString( '"sections"', $text );
	}

	public function test_no_pattern_means_no_pattern_instructions(): void {
		$result = ( new DraftPost() )->messages( array( 'topic' => 'composting' ) );

		$this->assertStringNotContainsString( 'pattern-search', $result['messages'][0]['content']['text'] );
	}

	public function test_tone_has_a_default(): void {
		$result = ( new DraftPost() )->messages( array( 'topic' => 'composting' ) );

		$this->assertStringContainsString( 'friendly', $result['messages'][0]['content']['text'] );
	}
}
