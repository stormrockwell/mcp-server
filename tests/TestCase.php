<?php
/**
 * Shared helpers for ability tests.
 *
 * @package McpServer
 */

declare( strict_types=1 );

namespace McpServer\Tests;

use WP_UnitTestCase;

abstract class TestCase extends WP_UnitTestCase {

	/**
	 * Runs an ability the way a client would: validation, permission, execute.
	 *
	 * @return array|\WP_Error
	 */
	protected function run_ability( string $name, array $input = array() ) {
		$ability = wp_get_ability( $name );
		$this->assertNotNull( $ability, "Ability {$name} is not registered." );

		// An ability without an input schema rejects any input, even an empty array.
		return $ability->get_input_schema() ? $ability->execute( $input ) : $ability->execute();
	}

	/**
	 * Creates a user with the role and makes them the current user.
	 */
	protected function act_as( string $role ): int {
		$user_id = self::factory()->user->create( array( 'role' => $role ) );
		wp_set_current_user( $user_id );

		return $user_id;
	}
}
