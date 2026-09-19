<?php
/**
 * Ability: search posts.
 *
 * @package McpServer
 */

declare( strict_types=1 );

namespace McpServer\Abilities;

use McpServer\Format;
use WP_Error;
use WP_Query;

/**
 * Finds posts of any public post type.
 */
class PostSearch extends Ability {

	/**
	 * {@inheritDoc}
	 */
	protected function slug(): string {
		return 'post-search';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function label(): string {
		return __( 'Search posts', 'mcp-server' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function description(): string {
		return __( 'Find posts, pages, and other public post types.', 'mcp-server' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function input_schema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'id'        => array(
					'type'        => 'integer',
					'description' => __( 'Return only the post with this ID. The defaults still filter: also pass "post_type" and "status" unless it is a published post.', 'mcp-server' ),
				),
				'search'    => array(
					'type'        => 'string',
					'description' => __( 'Words to look for in the title and content.', 'mcp-server' ),
				),
				'status'    => array(
					'type'        => 'string',
					'enum'        => array( 'publish', 'draft', 'pending', 'private', 'future', 'any' ),
					'description' => __( 'Defaults to "publish".', 'mcp-server' ),
				),
				'post_type' => array(
					'type'        => 'string',
					'description' => __( 'A public post type such as "post" or "page". Defaults to "post".', 'mcp-server' ),
				),
				'per_page'  => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'maximum'     => 50,
					'description' => __( 'Defaults to 10.', 'mcp-server' ),
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
				'posts' => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'id'      => array( 'type' => 'integer' ),
							'title'   => array( 'type' => 'string' ),
							'status'  => array( 'type' => 'string' ),
							'type'    => array( 'type' => 'string' ),
							'link'    => array( 'type' => 'string' ),
							'excerpt' => array( 'type' => 'string' ),
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
		$type_object = get_post_type_object( $input['post_type'] ?? 'post' );

		// An unknown post type falls back to edit_posts, so execute() can still explain what is wrong with it.
		return current_user_can( $type_object ? $type_object->cap->edit_posts : 'edit_posts' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $input Validated input.
	 */
	public function execute( array $input ) {
		$post_type   = $input['post_type'] ?? 'post';
		$type_object = get_post_type_object( $post_type );

		if ( ! $type_object || ! $type_object->public ) {
			return new WP_Error(
				'mcp_server_invalid_post_type',
				/* translators: %s: post type name */
				sprintf( __( '"%s" is not a public post type.', 'mcp-server' ), $post_type )
			);
		}

		$query = new WP_Query(
			array(
				'post_type'      => $post_type,
				'post_status'    => $input['status'] ?? 'publish',
				's'              => $input['search'] ?? '',
				'p'              => $input['id'] ?? 0,
				'posts_per_page' => $input['per_page'] ?? 10,
				'no_found_rows'  => true,
			)
		);

		$posts = array();
		foreach ( $query->posts as $post ) {
			// WP_Query does not check who is asking. This keeps other users' drafts and private posts out.
			if ( current_user_can( 'read_post', $post->ID ) ) {
				$posts[] = Format::post( $post );
			}
		}

		return array( 'posts' => $posts );
	}
}
