<?php

class WPML_Menu_Sync_Store {

	const KEY_PREFIX = 'wpml_menu_sync_menu_';

	const EXPIRY = 3600;

	public static function get() {
		$stored = get_transient( self::key() );

		return is_array( $stored ) ? $stored : null;
	}

	public static function save( $menus ) {
		if ( ! is_array( $menus ) ) {
			self::delete();

			return;
		}

		set_transient( self::key(), $menus, self::EXPIRY );
	}

	public static function delete() {
		delete_transient( self::key() );
	}

	private static function key() {
		return self::KEY_PREFIX . (int) get_current_user_id();
	}
}
