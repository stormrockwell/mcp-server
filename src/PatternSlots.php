<?php
/**
 * The editable parts of block markup: its words, images, and links.
 *
 * @package McpServer
 */

declare( strict_types=1 );

namespace McpServer;

use WP_Block_Type;
use WP_Block_Type_Registry;
use WP_Error;
use WP_HTML_Processor;
use WP_HTML_Tag_Processor;
use WP_HTML_Text_Replacement;

/**
 * Lists and fills the slots of block markup, so a model sends only the words and never the markup.
 *
 * A slot is a block attribute that block.json sources from the saved HTML, which is the same
 * rule block bindings use. Slots are numbered in document order.
 */
class PatternSlots {

	/**
	 * HTML attributes worth a slot. The rest, such as "rel" and "title", are rarely part of a layout's words.
	 */
	private const HTML_ATTRIBUTES = array( 'src', 'alt', 'href' );

	/**
	 * Tags a rich text value may contain. Anything else could close the block's own element.
	 */
	private const INLINE_TAGS = array( 'a', 'b', 'br', 'code', 'em', 'i', 'kbd', 'mark', 's', 'span', 'strong', 'sub', 'sup' );

	/**
	 * Lists the slots of some block markup.
	 *
	 * @param string $content Block markup.
	 * @return array<int, array{index: int, block: string, attribute: string, value: string}>
	 */
	public static function list( string $content ): array {
		$blocks = parse_blocks( $content );
		$slots  = array();

		foreach ( $blocks as &$block ) {
			self::walk( $block, $slots, array() );
		}

		return $slots;
	}

	/**
	 * Fills the slots of some block markup and returns the new markup.
	 *
	 * @param string                  $content Block markup.
	 * @param array<int, string|null> $values  New values by slot index. Null, or a missing index, keeps the original.
	 * @return string|WP_Error
	 */
	public static function fill( string $content, array $values ) {
		$blocks = parse_blocks( $content );
		$slots  = array();

		foreach ( $blocks as &$block ) {
			self::walk( $block, $slots, $values );
		}
		unset( $block );

		if ( $values && max( array_keys( $values ) ) >= count( $slots ) ) {
			return new WP_Error(
				'mcp_server_too_many_slots',
				/* translators: 1: number of values, 2: number of slots */
				sprintf( __( '%1$d values were given for %2$d slots. Call pattern-search with "name" to see the slots.', 'mcp-server' ), count( $values ), count( $slots ) )
			);
		}

		return serialize_blocks( $blocks );
	}

	/**
	 * Visits a block and its inner blocks in document order.
	 *
	 * @param array<string, mixed>             $block  Parsed block, changed in place when a value fills one of its slots.
	 * @param array<int, array<string, mixed>> $slots  Slots found so far.
	 * @param array<int, string|null>          $values New values by slot index.
	 */
	private static function walk( array &$block, array &$slots, array $values ): void {
		$type  = $block['blockName'] ? WP_Block_Type_Registry::get_instance()->get_registered( $block['blockName'] ) : null;
		$inner = 0;

		foreach ( $block['innerContent'] as $key => $chunk ) {
			if ( null === $chunk ) {
				self::walk( $block['innerBlocks'][ $inner++ ], $slots, $values );
			} elseif ( $type ) {
				$block['innerContent'][ $key ] = self::walk_html( $chunk, $type, $slots, $values );
			}
		}
	}

	/**
	 * Finds the slots in one piece of a block's own HTML and fills them.
	 *
	 * @param string                           $html   The HTML between two inner blocks, or all of it for a block without any.
	 * @param WP_Block_Type                    $type   The block's type.
	 * @param array<int, array<string, mixed>> $slots  Slots found so far.
	 * @param array<int, string|null>          $values New values by slot index.
	 */
	private static function walk_html( string $html, WP_Block_Type $type, array &$slots, array $values ): string {
		foreach ( $type->attributes ?? array() as $name => $definition ) {
			$source    = $definition['source'] ?? '';
			$attribute = $definition['attribute'] ?? '';
			$is_text   = in_array( $source, array( 'rich-text', 'html' ), true );

			if ( ! $is_text && ! ( 'attribute' === $source && in_array( $attribute, self::HTML_ATTRIBUTES, true ) ) ) {
				continue;
			}

			foreach ( explode( ',', $definition['selector'] ?? '' ) as $selector ) {
				$processor = self::processor( $html );

				// The HTML API has no CSS selectors yet, so only a plain tag name can be found.
				if ( ! ctype_alnum( $selector ) || ! $processor->next_tag( array( 'tag_name' => $selector ) ) ) {
					continue;
				}

				$range = $is_text ? $processor->inner_range() : null;
				if ( $is_text && ! $range ) {
					continue;
				}

				$index = count( $slots );
				$value = $is_text ? trim( substr( $html, $range[0], $range[1] ) ) : $processor->get_attribute( $attribute );
				$new   = $values[ $index ] ?? null;

				if ( is_string( $new ) ) {
					if ( $is_text ) {
						$new = self::inline( $new );
						$processor->replace( $range[0], $range[1], $new );
					} else {
						$processor->set_attribute( $attribute, $new );
					}

					$html  = $processor->get_updated_html();
					$value = $new;
				}

				$slots[] = array(
					'index'     => $index,
					'block'     => $type->name,
					'attribute' => $name,
					'value'     => is_string( $value ) ? $value : '',
				);
				break;
			}
		}

		return $html;
	}

	/**
	 * Strips a rich text value down to inline HTML, so it cannot close its element or open a block.
	 *
	 * @param string $value Rich text from the caller.
	 */
	private static function inline( string $value ): string {
		$allowed = array_intersect_key( wp_kses_allowed_html( 'post' ), array_flip( self::INLINE_TAGS ) );

		return wp_kses( preg_replace( '/<!--.*?(-->|$)/s', '', $value ), $allowed );
	}

	/**
	 * An HTML processor that can replace what is inside an element.
	 *
	 * Core has no public way to do this until WP_HTML_Processor::set_inner_html() lands. This is how
	 * WP_Block::replace_html() does it for block bindings, which is private.
	 *
	 * @param string $html HTML fragment.
	 */
	private static function processor( string $html ) {
		$processor = new class( '', WP_HTML_Processor::CONSTRUCTOR_UNLOCK_CODE ) extends WP_HTML_Processor {

			/**
			 * The byte offset and length of what the current element contains.
			 *
			 * @return array{0: int, 1: int}|null Null when the element does not close inside this HTML.
			 */
			public function inner_range(): ?array {
				if ( $this->is_tag_closer() || ! $this->expects_closer() ) {
					return null;
				}

				$depth = $this->get_current_depth();
				$tag   = $this->get_tag();

				// The processor prefixes bookmark names with "_".
				$this->set_bookmark( 'opener' );
				$start = $this->bookmarks['_opener']->start + $this->bookmarks['_opener']->length;

				while ( $this->next_token() && $this->get_current_depth() >= $depth ) {
					continue;
				}

				if ( ! $this->is_tag_closer() || $tag !== $this->get_tag() ) {
					return null;
				}

				/*
				 * The processor closes an element the HTML left open with a virtual token, which has
				 * no place in the text. Only the tokenizer underneath knows if this closer is real.
				 */
				if ( ! WP_HTML_Tag_Processor::is_tag_closer() ) {
					return null;
				}

				$this->set_bookmark( 'closer' );

				return array( $start, $this->bookmarks['_closer']->start - $start );
			}

			/**
			 * Queues a replacement of a byte range.
			 *
			 * @param int    $start  Byte offset.
			 * @param int    $length Byte length.
			 * @param string $html   Replacement HTML.
			 */
			public function replace( int $start, int $length, string $html ): void {
				$this->lexical_updates[] = new WP_HTML_Text_Replacement( $start, $length, $html );
			}
		};

		return $processor::create_fragment( $html );
	}
}
