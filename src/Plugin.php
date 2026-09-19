<?php
/**
 * Plugin bootstrap.
 *
 * @package McpServer
 */

declare( strict_types=1 );

namespace McpServer;

use McpServer\Abilities\Ability;
use McpServer\Prompts\Prompt;
use WP\MCP\Core\McpAdapter;

/**
 * Wires the plugin into WordPress.
 */
class Plugin {

	const CATEGORY = 'mcp-server';

	/**
	 * Registers hooks.
	 */
	public function init(): void {
		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
		add_action( 'mcp_adapter_init', array( $this, 'register_server' ) );
		add_action( 'admin_notices', array( $this, 'adapter_notice' ) );
	}

	/**
	 * Registers the category every ability in this plugin belongs to.
	 */
	public function register_category(): void {
		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => __( 'MCP Server', 'mcp-server' ),
				'description' => __( 'Abilities exposed by the MCP Server plugin.', 'mcp-server' ),
			)
		);
	}

	/**
	 * Registers every ability found in src/Abilities.
	 */
	public function register_abilities(): void {
		foreach ( $this->abilities() as $ability ) {
			$ability->register();
		}
	}

	/**
	 * Every concrete ability class in src/Abilities.
	 *
	 * @return Ability[]
	 */
	public function abilities(): array {
		return $this->discover( 'Abilities', Ability::class );
	}

	/**
	 * Registers the MCP server once the adapter is ready.
	 *
	 * @param McpAdapter $adapter The adapter.
	 */
	public function register_server( McpAdapter $adapter ): void {
		( new Server() )->register( $adapter, $this->abilities(), $this->prompts() );
	}

	/**
	 * Every concrete prompt class in src/Prompts.
	 *
	 * @return Prompt[]
	 */
	public function prompts(): array {
		return $this->discover( 'Prompts', Prompt::class );
	}

	/**
	 * Warns when the adapter is missing.
	 *
	 * The "Requires Plugins" header covers wp-admin activation only. WP-CLI and mu-plugin loading skip it.
	 */
	public function adapter_notice(): void {
		if ( class_exists( McpAdapter::class ) ) {
			return;
		}

		wp_admin_notice(
			__( 'MCP Server needs the MCP Adapter plugin. Install and activate it to expose your abilities over MCP.', 'mcp-server' ),
			array( 'type' => 'warning' )
		);
	}

	/**
	 * Instantiates every concrete class in a flat src folder that extends the base class.
	 *
	 * Dropping a file into the folder is all it takes to add one.
	 *
	 * @param string $folder Folder under src, which is also the namespace segment.
	 * @param string $base   Base class the classes must extend.
	 * @return object[]
	 */
	private function discover( string $folder, string $base ): array {
		$found = array();
		$files = glob( __DIR__ . "/{$folder}/*.php" );

		foreach ( $files ? $files : array() as $file ) {
			$class = __NAMESPACE__ . "\\{$folder}\\" . basename( $file, '.php' );

			if ( ! class_exists( $class ) ) {
				// The file name must match its class name, or the autoloader never finds it.
				_doing_it_wrong( __METHOD__, esc_html( "{$file} must declare the class {$class}." ), esc_html( MCP_SERVER_VERSION ) );
				continue;
			}

			if ( is_subclass_of( $class, $base ) && ! ( new \ReflectionClass( $class ) )->isAbstract() ) {
				$found[] = new $class();
			}
		}

		return $found;
	}
}
