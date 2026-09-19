<?php
/**
 * Base class for every MCP prompt in this plugin.
 *
 * @package McpServer
 */

declare( strict_types=1 );

namespace McpServer\Prompts;

/**
 * A prompt is a message template a person picks from their MCP client.
 *
 * It is not an ability: it does nothing on the site, so it stays out of the Abilities API.
 */
abstract class Prompt {

	/**
	 * How to fill a pattern's slots, shared by every prompt that writes in patterns.
	 *
	 * Models make up URLs that look real and do not load, and a sample image beats a broken one.
	 */
	protected function slots(): string {
		return __( 'Replace every sample text. Replace a sample image or link only with a URL the user gave you or one you have fetched, otherwise pass null, and let alt text follow its image.', 'mcp-server' );
	}

	/**
	 * Builds the adapter's prompt object.
	 *
	 * @return \WP\MCP\Domain\Prompts\McpPrompt|\WP_Error
	 */
	abstract public function build();
}
