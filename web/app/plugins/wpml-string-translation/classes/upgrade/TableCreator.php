<?php

namespace WPML\ST\Upgrade;

use WPML\Upgrade\CreateTable;

class TableCreator {

	public static function create( $wpdb, $table, $statement, $creator = CreateTable::class ) {
		$callback = [ $creator, 'create' ];
		if ( ! class_exists( $creator ) || ! is_callable( $callback ) ) {
			return false;
		}

		return (bool) call_user_func( $callback, $wpdb, $table, $statement );
	}
}
