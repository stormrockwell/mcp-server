<?php
/**
 * Ability: search block patterns.
 *
 * @package McpServer
 */

declare( strict_types=1 );

namespace McpServer\Abilities;

use McpServer\PatternSlots;
use WP_Block_Patterns_Registry;
use WP_Error;

/**
 * Finds registered block patterns, so a post can be written into a fixed layout.
 */
class PatternSearch extends Ability {

	/**
	 * {@inheritDoc}
	 */
	public function name(): string {
		return 'mcp-server/pattern-search';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function label(): string {
		return __( 'Search patterns', 'mcp-server' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function description(): string {
		return __( 'Find block patterns by search term or category. Pass "name" to get one pattern with its slots: the sample text, images, and links you can replace. To build a post from patterns, pass each pattern name and its new slot values to post-save as "sections".', 'mcp-server' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function input_schema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'name'     => array(
					'type'        => 'string',
					'description' => 'Exact pattern name, for example "core/query-grid-posts".',
				),
				'markup'   => array(
					'type'        => 'boolean',
					'description' => 'With "name", also return the block markup. Only needed to change more than the slots.',
				),
				'search'   => array(
					'type'        => 'string',
					'description' => 'Words to look for in the name, title, and description.',
				),
				'category' => array(
					'type'        => 'string',
					'description' => 'A pattern category slug such as "posts" or "text".',
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
				'patterns' => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'name'        => array( 'type' => 'string' ),
							'title'       => array( 'type' => 'string' ),
							'description' => array( 'type' => 'string' ),
							'categories'  => array(
								'type'  => 'array',
								'items' => array( 'type' => 'string' ),
							),
							'slots'       => array(
								'type'        => 'array',
								'description' => 'Only present when "name" was passed. Text slots hold inline HTML, the others hold a URL or plain text.',
								'items'       => array(
									'type'       => 'object',
									'properties' => array(
										'index'     => array( 'type' => 'integer' ),
										'block'     => array( 'type' => 'string' ),
										'attribute' => array( 'type' => 'string' ),
										'value'     => array( 'type' => 'string' ),
									),
								),
							),
							'content'     => array(
								'type'        => 'string',
								'description' => 'Block markup. Only present when "markup" was passed.',
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
		// The same floor as the block editor, the only other place patterns are listed.
		return current_user_can( 'edit_posts' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $input Validated input.
	 */
	public function execute( array $input ) {
		$registry = WP_Block_Patterns_Registry::get_instance();

		if ( isset( $input['name'] ) ) {
			$pattern = $registry->get_registered( $input['name'] );

			if ( ! $pattern ) {
				return new WP_Error(
					'mcp_server_pattern_not_found',
					/* translators: %s: pattern name */
					sprintf( __( 'No pattern is named "%s". Call pattern-search without "name" to list the right ones.', 'mcp-server' ), $input['name'] )
				);
			}

			$formatted          = $this->format( $pattern );
			$formatted['slots'] = PatternSlots::list( $pattern['content'] );

			// The markup is several times the size of the slots, and filling slots never needs it.
			if ( ! empty( $input['markup'] ) ) {
				$formatted['content'] = $pattern['content'];
			}

			return array( 'patterns' => array( $formatted ) );
		}

		$patterns = array();
		foreach ( $registry->get_all_registered() as $pattern ) {
			if ( $this->matches( $pattern, $input ) ) {
				// A site has a hundred or more patterns, and their slots would bury the list.
				$patterns[] = $this->format( $pattern );
			}
		}

		return array( 'patterns' => $patterns );
	}

	/**
	 * Whether a pattern passes the "search" and "category" filters.
	 *
	 * @param array<string, mixed> $pattern Registered pattern properties.
	 * @param array<string, mixed> $input   Validated input.
	 */
	private function matches( array $pattern, array $input ): bool {
		if ( isset( $input['category'] ) && ! in_array( $input['category'], $pattern['categories'] ?? array(), true ) ) {
			return false;
		}

		$haystack = $pattern['name'] . ' ' . $pattern['title'] . ' ' . ( $pattern['description'] ?? '' );

		return ! isset( $input['search'] ) || false !== stripos( $haystack, $input['search'] );
	}

	/**
	 * Shapes a pattern to match the output schema.
	 *
	 * @param array<string, mixed> $pattern Registered pattern properties.
	 * @return array<string, mixed>
	 */
	private function format( array $pattern ): array {
		return array(
			'name'        => $pattern['name'],
			'title'       => $pattern['title'],
			'description' => $pattern['description'] ?? '',
			'categories'  => array_values( $pattern['categories'] ?? array() ),
		);
	}
}
