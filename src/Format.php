<?php
/**
 * Shared output shaping for abilities.
 *
 * @package McpServer
 */

declare( strict_types=1 );

namespace McpServer;

use WP_Post;
use WP_Term;

/**
 * Every ability that returns a post or a menu returns the same shape, so those shapes live here once.
 */
class Format {

	/**
	 * Shapes a post to match an ability's output schema.
	 *
	 * @param WP_Post $post The post.
	 * @return array<string, mixed>
	 */
	public static function post( WP_Post $post ): array {
		return array(
			'id'      => $post->ID,
			// The raw title: get_the_title() adds "Private: " and entities, which a search then save would write back.
			'title'   => $post->post_title,
			'status'  => $post->post_status,
			'type'    => $post->post_type,
			'link'    => (string) get_permalink( $post ),
			// A password protected post is readable by anyone with read_post, so its excerpt must not leak.
			'excerpt' => $post->post_password ? '' : wp_trim_words( wp_strip_all_tags( $post->post_content ), 30 ),
		);
	}

	/**
	 * Shapes a menu to match an ability's output schema.
	 *
	 * @param WP_Term $menu The menu term.
	 * @return array<string, mixed>
	 */
	public static function menu( WP_Term $menu ): array {
		$items = array();
		foreach ( (array) wp_get_nav_menu_items( $menu->term_id ) as $item ) {
			$items[] = array(
				'id'    => (int) $item->ID,
				'title' => (string) $item->title,
				'url'   => (string) $item->url,
			);
		}

		return array(
			'id'    => $menu->term_id,
			'name'  => $menu->name,
			'items' => $items,
		);
	}
}
