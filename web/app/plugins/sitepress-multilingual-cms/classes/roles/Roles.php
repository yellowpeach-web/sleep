<?php

namespace WPML;

use WPML\LIB\WP\Hooks;
use WPML\LIB\WP\User;
use WPML\LIB\WP\Roles as WPRoles;
use function WPML\FP\spreadArgs;

class Roles implements \IWPML_Backend_Action, \IWPML_AJAX_Action {

	public function add_hooks() {
		Hooks::onAction( 'set_user_role', 10, 3 )->then( spreadArgs( [ self::class, 'remove_caps' ] ) );
		Hooks::onAction( 'init' )->then( spreadArgs( [ self::class, 'ensure_admin_can_manage_translations' ] ) );
	}

	public static function remove_caps( $userId, $role, $oldRoles ) {
		if ( ! WPRoles::hasCap( 'manage_options', $role ) ) {
			$user = User::get( $userId );

			wpml_collect( DefaultCapabilities::getKeys() )
				->map( [ $user, 'remove_cap' ] );
		}
	}

	public static function ensure_admin_can_manage_translations() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( self::current_user_has_admin_caps() ) {
			return;
		}
		self::ensure_manage_translations_cap();
		self::ensure_wpml_caps();
		wp_get_current_user()->get_role_caps();
	}

	private static function current_user_has_admin_caps() {
		foreach ( array_merge( [ User::CAP_MANAGE_TRANSLATIONS ], wpml_get_capability_keys() ) as $cap ) {
			if ( ! current_user_can( $cap ) ) {
				return false;
			}
		}

		return true;
	}

	public static function ensure_wpml_caps() {
		$role = get_role( 'administrator' );
		if ( ! $role ) {
			return;
		}
		foreach ( wpml_get_capability_keys() as $cap ) {
			if ( ! array_key_exists( $cap, $role->capabilities ) ) {
				$role->add_cap( $cap );
			}
		}
	}

	public static function ensure_manage_translations_cap() {
		$role = get_role( 'administrator' );
		if ( $role && empty( $role->capabilities[ User::CAP_MANAGE_TRANSLATIONS ] ) ) {
			$role->add_cap( User::CAP_MANAGE_TRANSLATIONS );
		}
	}
}
