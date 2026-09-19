<?php
/**
 * Tests for listing and filling the editable slots of block markup.
 *
 * @package McpServer
 */

declare( strict_types=1 );

namespace McpServer\Tests;

use McpServer\PatternSlots;
use WP_Block_Patterns_Registry;

final class PatternSlotsTest extends TestCase {

	private const CONTENT = '<!-- wp:group {"layout":{"type":"constrained"}} -->
<div class="wp-block-group">
	<!-- wp:heading {"fontSize":"x-large"} -->
	<h2 class="wp-block-heading has-x-large-font-size">
		Old heading	</h2>
	<!-- /wp:heading -->
	<!-- wp:paragraph -->
	<p>Old <strong>intro</strong></p>
	<!-- /wp:paragraph -->
	<!-- wp:image {"sizeSlug":"full"} -->
	<figure class="wp-block-image size-full"><img src="http://example.org/old.webp" alt="Old alt"/></figure>
	<!-- /wp:image -->
	<!-- wp:buttons -->
	<div class="wp-block-buttons"><!-- wp:button -->
	<div class="wp-block-button"><a class="wp-block-button__link wp-element-button">Learn more</a></div>
	<!-- /wp:button --></div>
	<!-- /wp:buttons -->
</div>
<!-- /wp:group -->';

	/**
	 * The block comments of some markup, in order: its structure without its words.
	 *
	 * @return string[]
	 */
	private function structure( string $content ): array {
		// Serializing re-encodes the attribute JSON, so both sides go through it.
		preg_match_all( '/<!-- \/?wp:.*?-->/s', serialize_blocks( parse_blocks( $content ) ), $matches );

		return $matches[0];
	}

	public function test_lists_text_image_and_link_slots_in_document_order(): void {
		$slots = PatternSlots::list( self::CONTENT );

		$this->assertSame(
			array(
				array( 'core/heading', 'content', 'Old heading' ),
				array( 'core/paragraph', 'content', 'Old <strong>intro</strong>' ),
				array( 'core/image', 'url', 'http://example.org/old.webp' ),
				array( 'core/image', 'alt', 'Old alt' ),
				array( 'core/button', 'url', '' ),
				array( 'core/button', 'text', 'Learn more' ),
			),
			array_map( static fn( $slot ) => array( $slot['block'], $slot['attribute'], $slot['value'] ), $slots )
		);
		$this->assertSame( range( 0, 5 ), wp_list_pluck( $slots, 'index' ) );
	}

	public function test_fill_replaces_the_words_and_keeps_the_structure(): void {
		$filled = PatternSlots::fill(
			self::CONTENT,
			array( 'New heading', 'New <em>intro</em>', 'https://example.org/cat.jpg?w=1&h=2', 'A cat', 'https://example.org/more', 'Read on' )
		);

		$this->assertSame( $this->structure( self::CONTENT ), $this->structure( $filled ) );
		$this->assertStringContainsString( '<h2 class="wp-block-heading has-x-large-font-size">New heading</h2>', $filled );
		$this->assertStringContainsString( '<p>New <em>intro</em></p>', $filled );
		$this->assertStringContainsString( 'alt="A cat"', $filled );
		$this->assertStringContainsString( 'Read on</a>', $filled );
		$this->assertStringNotContainsString( 'Old', $filled );

		$refilled = wp_list_pluck( PatternSlots::list( $filled ), 'value' );
		$this->assertSame( 'https://example.org/cat.jpg?w=1&h=2', $refilled[2] );
		$this->assertSame( 'https://example.org/more', $refilled[4] );
	}

	public function test_a_null_or_missing_value_keeps_the_original(): void {
		$filled = PatternSlots::fill( self::CONTENT, array( null, 'Only the intro' ) );
		$values = wp_list_pluck( PatternSlots::list( $filled ), 'value' );

		$this->assertSame( 'Old heading', $values[0] );
		$this->assertSame( 'Only the intro', $values[1] );
		$this->assertSame( 'Old alt', $values[3] );
	}

	public function test_too_many_values_is_an_error(): void {
		$result = PatternSlots::fill( self::CONTENT, array_fill( 0, 7, 'x' ) );

		$this->assertWPError( $result );
		$this->assertSame( 'mcp_server_too_many_slots', $result->get_error_code() );
	}

	public function test_a_value_cannot_break_out_of_its_block(): void {
		$filled = PatternSlots::fill( self::CONTENT, array( 'Hi</h2><!-- /wp:heading --><script>x</script><div>' ) );

		$this->assertSame( $this->structure( self::CONTENT ), $this->structure( $filled ) );
		$this->assertStringNotContainsString( '<script', $filled );
		$this->assertStringNotContainsString( '<div>', $filled );
	}

	public function test_text_before_a_nested_block_is_left_alone(): void {
		$content = '<!-- wp:details --><details class="wp-block-details"><summary>Old summary</summary><!-- wp:paragraph --><p>Hidden</p><!-- /wp:paragraph --></details><!-- /wp:details -->';

		$filled = PatternSlots::fill( $content, array( 'New summary', 'Shown' ) );

		$this->assertStringContainsString( '<summary>New summary</summary>', $filled );
		$this->assertStringContainsString( '<p>Shown</p>', $filled );
		$this->assertSame( $this->structure( $content ), $this->structure( $filled ) );
	}

	public function test_an_element_that_wraps_inner_blocks_is_not_a_slot(): void {
		$content = '<!-- wp:quote --><blockquote class="wp-block-quote"><!-- wp:paragraph --><p>Quoted</p><!-- /wp:paragraph --><cite>Someone</cite></blockquote><!-- /wp:quote -->';

		$slots = PatternSlots::list( $content );

		$this->assertSame( array( 'content', 'citation' ), wp_list_pluck( $slots, 'attribute' ) );
		$this->assertSame( array( 'Quoted', 'Someone' ), wp_list_pluck( $slots, 'value' ) );
	}

	public function test_every_registered_pattern_survives_a_fill(): void {
		$patterns = WP_Block_Patterns_Registry::get_instance()->get_all_registered();
		$this->assertNotEmpty( $patterns );

		foreach ( $patterns as $pattern ) {
			$slots  = PatternSlots::list( $pattern['content'] );
			$values = array_map(
				static fn( $slot ) => in_array( $slot['attribute'], array( 'content', 'text', 'value', 'citation', 'summary' ), true ) ? 'Filled' : 'https://example.org/x',
				$slots
			);
			$filled = PatternSlots::fill( $pattern['content'], $values );

			$this->assertIsString( $filled, $pattern['name'] );
			$this->assertSame( $this->structure( $pattern['content'] ), $this->structure( $filled ), $pattern['name'] );
			$this->assertSame( $values, wp_list_pluck( PatternSlots::list( $filled ), 'value' ), $pattern['name'] );
		}
	}
}
