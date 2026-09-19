<?php
/**
 * Base class for every ability in this plugin.
 *
 * @package McpServer
 */

declare( strict_types=1 );

namespace McpServer\Abilities;

use McpServer\Plugin;

/**
 * One ability, one class.
 *
 * Every method maps onto one wp_register_ability() argument, so anything
 * learned here carries over to the core Abilities API.
 *
 * Every class in this folder registers itself, see Plugin::discover().
 */
abstract class Ability {

	/**
	 * Unique name, for example "mcp-server/post-search".
	 */
	abstract public function name(): string;

	/**
	 * Short human label.
	 */
	abstract protected function label(): string;

	/**
	 * What the ability does. The model reads this to decide when to call it.
	 */
	abstract protected function description(): string;

	/**
	 * JSON Schema for the input. Return an empty array for no input.
	 *
	 * @return array<string, mixed>
	 */
	abstract protected function input_schema(): array;

	/**
	 * JSON Schema for the output. MCP needs "type" to be "object".
	 *
	 * @return array<string, mixed>
	 */
	abstract protected function output_schema(): array;

	/**
	 * Whether the current user may run this with the given input.
	 *
	 * Core replaces any failed result with one generic error, so actionable errors belong in execute() instead.
	 *
	 * @param array<string, mixed> $input Validated input.
	 * @return bool
	 */
	abstract public function permission( array $input );

	/**
	 * Does the work.
	 *
	 * @param array<string, mixed> $input Validated input.
	 * @return array<string, mixed>|\WP_Error
	 */
	abstract public function execute( array $input );

	/**
	 * MCP component type: "tool" or "resource".
	 */
	public function type(): string {
		return 'tool';
	}

	/**
	 * True when the tool changes nothing. Clients may auto approve these.
	 */
	protected function readonly(): bool {
		return false;
	}

	/**
	 * Extra keys for meta.mcp, such as a resource "uri".
	 *
	 * @return array<string, mixed>
	 */
	protected function mcp_meta(): array {
		return array();
	}

	/**
	 * Registers the ability with WordPress.
	 */
	public function register(): void {
		$meta = array(
			'mcp' => array_merge( array( 'type' => $this->type() ), $this->mcp_meta() ),
		);

		// A resource is read only by nature, so only a tool needs one.
		if ( 'tool' === $this->type() ) {
			$meta['annotations'] = array( 'readonly' => $this->readonly() );
		}

		wp_register_ability(
			$this->name(),
			array(
				'label'               => $this->label(),
				'description'         => $this->description(),
				'category'            => Plugin::CATEGORY,
				'input_schema'        => $this->input_schema(),
				'output_schema'       => $this->output_schema(),
				'permission_callback' => fn( $input = null ) => $this->permission( (array) $input ),
				'execute_callback'    => fn( $input = null ) => $this->execute( (array) $input ),
				'meta'                => $meta,
			)
		);
	}
}
