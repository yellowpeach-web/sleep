<?php

namespace WPML\TM\Menu\TranslationRoles;

use WPML\FP\Obj;

class RoleValidator {

	public static function isValid( $roleName ) {
		$wp_role = get_role( $roleName );
		return $wp_role instanceof \WP_Role;
	}

	public static function getTheHighestPossibleIfNotValid( $roleName ) {
		$wp_role = get_role( $roleName );
		$user    = wp_get_current_user();
		if ( ! self::isEditable( $roleName ) || ! \WPML_WP_Roles::is_role_up_to_user( $wp_role->capabilities, $user ) ) {
			$roleName = self::getTheHighestRole( \WPML_WP_Roles::get_roles_up_to_user_level( $user ) );
		}

		return $roleName;
	}

	private static function isEditable( $roleName ) {
		return ! function_exists( 'get_editable_roles' ) || array_key_exists( $roleName, get_editable_roles() );
	}

	private static function getTheHighestRole( array $roles ) {
		$highestRole  = null;
		$highestLevel = -1;
		foreach ( $roles as $role ) {
			$roleId = Obj::prop( 'id', $role );
			$wpRole = get_role( $roleId );
			$level  = $wpRole instanceof \WP_Role ? (int) \WPML_WP_Roles::get_highest_level( $wpRole->capabilities ) : 0;
			if ( $level > $highestLevel ) {
				$highestRole  = $roleId;
				$highestLevel = $level;
			}
		}

		return $highestRole;
	}
}
