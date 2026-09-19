<?php
/**
 * Prompt: draft a blog post.
 *
 * @package McpServer
 */

declare( strict_types=1 );

namespace McpServer\Prompts;

use WP\MCP\Domain\Prompts\McpPrompt;

/**
 * Asks the model to write a post and save it as a draft.
 */
class DraftPost extends Prompt {

	/**
	 * {@inheritDoc}
	 */
	public function build() {
		return McpPrompt::fromArray(
			array(
				'name'        => 'draft-post',
				'title'       => __( 'Draft a post', 'mcp-server' ),
				'description' => __( 'Write a blog post about a topic and save it as a draft.', 'mcp-server' ),
				'arguments'   => array(
					array(
						'name'        => 'topic',
						'description' => __( 'What the post is about.', 'mcp-server' ),
						'required'    => true,
					),
					array(
						'name'        => 'tone',
						'description' => __( 'Writing tone, for example "friendly" or "formal".', 'mcp-server' ),
						'required'    => false,
					),
					array(
						'name'        => 'pattern',
						'description' => __( 'Name of a block pattern to write the post in, for example "my-theme/article".', 'mcp-server' ),
						'required'    => false,
					),
				),
				'handler'     => array( $this, 'messages' ),
				'permission'  => static fn() => current_user_can( get_post_type_object( 'post' )->cap->create_posts ),
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
		// A pattern fixes the layout, so every post drafted with it looks the same. Without one the model picks its own.
		if ( empty( $args['pattern'] ) ) {
			$layout = __( 'Give it an introduction, three to five sections with headings, and a conclusion.', 'mcp-server' );
		} else {
			$layout = sprintf(
				/* translators: 1: pattern name, 2: instructions for filling the pattern. Keep the tool names pattern-search and post-save, and "sections", as they are. */
				__( 'Fetch the slots of the pattern "%1$s" with pattern-search and pass it to post-save as "sections". %2$s', 'mcp-server' ),
				$args['pattern'],
				$this->slots()
			);
		}

		$text = sprintf(
			/* translators: 1: topic, 2: writing tone, 3: layout instructions. Keep the tool name post-save as it is. */
			__( 'Write a blog post about "%1$s" in a %2$s tone. %3$s Save it with post-save as a draft.', 'mcp-server' ),
			$args['topic'] ?? '',
			$args['tone'] ?? __( 'friendly', 'mcp-server' ),
			$layout
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
