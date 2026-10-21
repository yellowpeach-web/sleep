<?php

class WPML_WP_Roles {
	const ROLES_ADMINISTRATOR = 'administrator';
	const ROLES_EDITOR        = 'editor';
	const ROLES_CONTRIBUTOR   = 'contributor';
	const ROLES_SUBSCRIBER    = 'subscriber';

	const EDITOR_LEVEL      = 'level_7';
	const CONTRIBUTOR_LEVEL = 'level_1';
	const SUBSCRIBER_LEVEL  = 'level_0';

	public static function get_editor_roles() {
		return self::get_roles_for_level( self::EDITOR_LEVEL, self::ROLES_EDITOR );
	}

	public static function get_contributor_roles() {
		return self::get_roles_for_level( self::CONTRIBUTOR_LEVEL, self::ROLES_CONTRIBUTOR );
	}

	public static function get_subscriber_roles() {
		return self::get_roles_for_level( self::SUBSCRIBER_LEVEL, self::ROLES_SUBSCRIBER );
	}

	public static function get_roles_up_to_user_level( WP_User $user, $default = self::ROLES_SUBSCRIBER ) {
		$isRoleUpToUser = function ( $role ) use ( $user ) {
			return self::is_role_up_to_user( $role['capabilities'], $user );
		};

		return \wpml_collect( get_editable_roles() )
			->filter( $isRoleUpToUser )
			->map( self::create_build_role_entity( self::get_user_max_level( $user ), $default ) )
			->values()
			->toArray();
	}

	public static function is_role_up_to_user( array $capabilities, WP_User $user ) {
		if ( is_multisite() && is_super_admin( $user->ID ) ) {
			return true;
		}

		$userLevel = self::get_user_max_level( $user );
		if ( self::get_highest_level( $capabilities ) > $userLevel ) {
			return false;
		}

		if ( ! empty( $capabilities['manage_translations'] ) && ! self::user_holds( $user, 'manage_options' ) ) {
			return false;
		}

		if ( $userLevel >= 10 ) {
			return true;
		}

		foreach ( $capabilities as $cap => $granted ) {
			if ( $granted && strpos( $cap, 'level_' ) !== 0 && ! self::user_holds( $user, $cap ) ) {
				return false;
			}
		}

		return true;
	}

	private static function user_holds( WP_User $user, $cap ) {
		return ! empty( $user->allcaps[ $cap ] ) || $user->has_cap( $cap );
	}

	public static function get_user_max_level( WP_User $user ) {
		return self::get_highest_level( $user->get_role_caps() );
	}

	public static function get_highest_level( array $capabilities ) {
		$capabilitiesWithLevel = function ( $has, $cap ) {
			return $has && strpos( $cap, 'level_' ) === 0;
		};
		$levelToNumber         = function ( $cap ) {
			return (int) substr( $cap, strlen( 'level_' ) );
		};

		return \wpml_collect( $capabilities )
			->filter( $capabilitiesWithLevel )
			->keys()
			->map( $levelToNumber )
			->sort()
			->last();
	}

	private static function get_roles_for_level( $level, $default = null ) {
		return \wpml_collect( get_editable_roles() )
			->filter(
				function ( $role ) use ( $level ) {
					return isset( $role['capabilities'][ $level ] ) && $role['capabilities'][ $level ];
				}
			)
			->map( self::create_build_role_entity( $level, $default ) )
			->values()
			->toArray();
	}

	private static function create_build_role_entity( $level, $default = null ) {
		$is_default = self::create_is_default( $level, $default );

		return function ( $role, $id ) use ( $is_default ) {
			return [
				'id'      => $id,
				'name'    => $role['name'],
				'default' => $is_default( $id ),
			];
		};
	}

	private static function create_is_default( $level, $default = null ) {
		$default = apply_filters( 'wpml_role_for_level_default', $default, $level );
		return function ( $id ) use ( $default ) {
			return $default && ( $default === $id );
		};
	}
}
