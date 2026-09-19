<?php
/**
 * Prompt: draft a page.
 *
 * @package McpServer
 */

declare( strict_types=1 );

namespace McpServer\Prompts;

use WP\MCP\Domain\Prompts\McpPrompt;

/**
 * Asks the model to build a page out of block patterns and save it as a draft.
 */
class DraftPage extends Prompt {

	/**
	 * {@inheritDoc}
	 */
	public function build() {
		return McpPrompt::fromArray(
			array(
				'name'        => 'draft-page',
				'title'       => __( 'Draft a page', 'mcp-server' ),
				'description' => __( 'Build a page about a topic from block patterns and save it as a draft.', 'mcp-server' ),
				'arguments'   => array(
					array(
						'name'        => 'topic',
						'description' => __( 'What the page is about.', 'mcp-server' ),
						'required'    => true,
					),
					array(
						'name'        => 'tone',
						'description' => __( 'Writing tone, for example "friendly" or "formal".', 'mcp-server' ),
						'required'    => false,
					),
					array(
						'name'        => 'patterns',
						'description' => __( 'Block patterns to build the page from, top to bottom, separated by commas. Leave out to let the model choose.', 'mcp-server' ),
						'required'    => false,
					),
				),
				'handler'     => array( $this, 'messages' ),
				'permission'  => static fn() => current_user_can( get_post_type_object( 'page' )->cap->create_posts ),
			)
		);
	}

	/**
	 * The messages handed to the model when someone picks this prompt.
	 *
	 * @param array<string, string> $args Prompt arguments.
	 * @return array<string, mixed>
	 */
	public function messages( array $args ): array {
		// A post is one article, so draft-post takes one pattern. A page is a stack of sections, so this takes several.
		if ( empty( $args['patterns'] ) ) {
			$choice = __( 'Pick three to five content patterns with pattern-search: an opener, detail sections, and a closing call to action or FAQ.', 'mcp-server' );
		} else {
			/* translators: %s: comma separated pattern names. */
			$choice = sprintf( __( 'Use these patterns, in order: %s.', 'mcp-server' ), $args['patterns'] );
		}

		$text = sprintf(
			/* translators: 1: topic, 2: writing tone, 3: which patterns to use, 4: instructions for filling the patterns. Keep the tool names pattern-search and post-save, and the quoted field names, as they are. */
			__( 'Build a page about "%1$s" in a %2$s tone. %3$s Fetch each pattern\'s slots with pattern-search. %4$s Save it with post-save as a draft, with "post_type" set to "page" and the patterns as "sections".', 'mcp-server' ),
			$args['topic'] ?? '',
			$args['tone'] ?? __( 'friendly', 'mcp-server' ),
			$choice,
			$this->slots()
		);

		return array(
			'messages' => array(
				array(
					'role'    => 'user',
					'content' => array(
						'type' => 'text',
						'text' => $text,
					),
				),
			),
		);
	}
}
