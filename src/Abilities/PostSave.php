<?php
/**
 * Ability: create or update a post.
 *
 * @package McpServer
 */

declare( strict_types=1 );

namespace McpServer\Abilities;

use McpServer\Format;
use McpServer\PatternSlots;
use WP_Block_Patterns_Registry;
use WP_Error;

/**
 * Creates a post, or updates one when an ID is given, like wp_insert_post().
 */
class PostSave extends Ability {

	/**
	 * {@inheritDoc}
	 */
	public function name(): string {
		return 'mcp-server/post-save';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function label(): string {
		return __( 'Save post', 'mcp-server' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function description(): string {
		return __( 'Create or update a post, page, or other public post type. An update changes only the fields you pass. New posts are drafts.', 'mcp-server' );
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
					'description' => 'Post to update. Leave out to create.',
				),
				'title'     => array(
					'type'        => 'string',
					'description' => 'Required when creating.',
				),
				'content'   => array(
					'type'        => 'string',
					'description' => 'Post content as HTML or block markup. To build from patterns, pass "sections" instead.',
				),
				'sections'  => array(
					'type'        => 'array',
					'description' => 'Post content as a list of filled patterns, in place of "content". Replaces all existing content.',
					'items'       => array(
						'type'       => 'object',
						'required'   => array( 'pattern' ),
						'properties' => array(
							'pattern' => array(
								'type'        => 'string',
								'description' => 'A pattern name from pattern-search.',
							),
							'slots'   => array(
								'type'        => 'array',
								'description' => 'New values, in the slot order pattern-search returns. Null keeps the sample value, as does leaving values off the end.',
								'items'       => array( 'type' => array( 'string', 'null' ) ),
							),
						),
					),
				),
				'status'    => array(
					'type' => 'string',
					'enum' => array( 'draft', 'pending', 'private', 'publish' ),
				),
				'post_type' => array(
					'type'        => 'string',
					'description' => 'Used only when creating. Defaults to "post".',
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
				'post' => array(
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
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $input Validated input.
	 */
	public function permission( array $input ) {
		$post = empty( $input['id'] ) ? null : get_post( (int) $input['id'] );

		if ( $post ) {
			return current_user_can( 'edit_post', $post->ID );
		}

		/*
		 * A missing post falls through to the create check. Core turns every permission
		 * failure into one generic error, so only execute() can tell the caller the ID is wrong.
		 */
		$type_object = get_post_type_object( $input['post_type'] ?? 'post' );

		return $type_object && current_user_can( $type_object->cap->create_posts );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $input Validated input.
	 */
	public function execute( array $input ) {
		$id       = (int) ( $input['id'] ?? 0 );
		$existing = $id ? get_post( $id ) : null;

		if ( $id && ! $existing ) {
			return new WP_Error(
				'mcp_server_post_not_found',
				/* translators: %d: post ID */
				sprintf( __( 'No post has the ID %d. Use post-search to find the right one.', 'mcp-server' ), $id )
			);
		}

		$post_type   = $existing ? $existing->post_type : ( $input['post_type'] ?? 'post' );
		$type_object = get_post_type_object( $post_type );

		if ( ! $type_object || ! $type_object->public ) {
			return new WP_Error(
				'mcp_server_invalid_post_type',
				/* translators: %s: post type name */
				sprintf( __( '"%s" is not a public post type.', 'mcp-server' ), $post_type )
			);
		}

		if ( ! $id && empty( $input['title'] ) ) {
			return new WP_Error(
				'mcp_server_missing_title',
				__( '"title" is required when creating a post.', 'mcp-server' )
			);
		}

		// Core requires publish_posts for private too: a private post is as visible to its author as a published one is to everyone.
		if ( in_array( $input['status'] ?? '', array( 'publish', 'private' ), true ) && ! current_user_can( $type_object->cap->publish_posts ) ) {
			return new WP_Error(
				'mcp_server_cannot_publish',
				__( 'You are not allowed to publish. Save with the status "pending" instead.', 'mcp-server' )
			);
		}

		if ( isset( $input['sections'] ) ) {
			if ( isset( $input['content'] ) ) {
				return new WP_Error(
					'mcp_server_content_conflict',
					__( 'Pass "content" or "sections", not both: each one is the whole post content.', 'mcp-server' )
				);
			}

			$input['content'] = $this->content_from_sections( $input['sections'] );

			if ( is_wp_error( $input['content'] ) ) {
				return $input['content'];
			}
		}

		$postarr = $id ? array( 'ID' => $id ) : array(
			'post_type'   => $post_type,
			'post_status' => 'draft',
		);

		$fields = array(
			'title'   => 'post_title',
			'content' => 'post_content',
			'status'  => 'post_status',
		);

		foreach ( $fields as $input_key => $post_key ) {
			if ( isset( $input[ $input_key ] ) ) {
				$postarr[ $post_key ] = $input[ $input_key ];
			}
		}

		// Core expects slashed data and strips unsafe HTML for users without unfiltered_html.
		$postarr = wp_slash( $postarr );
		$result  = $id ? wp_update_post( $postarr, true ) : wp_insert_post( $postarr, true );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array( 'post' => Format::post( get_post( $result ) ) );
	}

	/**
	 * Builds block markup from patterns and the values for their slots.
	 *
	 * @param array<int, array<string, mixed>> $sections Validated "sections" input.
	 * @return string|WP_Error
	 */
	private function content_from_sections( array $sections ) {
		$content = array();

		foreach ( $sections as $index => $section ) {
			$pattern = WP_Block_Patterns_Registry::get_instance()->get_registered( $section['pattern'] );

			if ( ! $pattern ) {
				return new WP_Error(
					'mcp_server_pattern_not_found',
					/* translators: %s: pattern name */
					sprintf( __( 'No pattern is named "%s". Use pattern-search to find the right one.', 'mcp-server' ), $section['pattern'] )
				);
			}

			$filled = PatternSlots::fill( $pattern['content'], $section['slots'] ?? array() );

			if ( is_wp_error( $filled ) ) {
				// A page has many sections, and the error alone does not say which one to fix.
				return new WP_Error(
					$filled->get_error_code(),
					/* translators: 1: section number, 2: pattern name, 3: error message */
					sprintf( __( 'Section %1$d (%2$s): %3$s', 'mcp-server' ), $index + 1, $section['pattern'], $filled->get_error_message() )
				);
			}

			$content[] = trim( $filled );
		}

		return implode( "\n\n", $content );
	}
}
