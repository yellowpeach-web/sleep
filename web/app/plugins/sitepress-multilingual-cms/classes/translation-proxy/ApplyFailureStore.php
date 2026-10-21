<?php

namespace WPML\TM\TranslationProxy;

class ApplyFailureStore {

	const ROW_PREFIX = '_wpml_tp_apply_failure_';

	const PURGE_BATCH = 500;

	const PURGE_STAMP = '_wpml_tp_apply_failure_purged';

	public static function get( $tp_id ) {
		if ( ! $tp_id ) {
			return null;
		}

		$record = get_option( self::rowName( $tp_id ), null );

		return is_array( $record ) ? $record : null;
	}

	public static function put( $tp_id, array $record ) {
		if ( ! $tp_id ) {
			return;
		}

		update_option( self::rowName( $tp_id ), $record, false );
	}

	public static function forget( $tp_id ) {
		if ( $tp_id ) {
			delete_option( self::rowName( $tp_id ) );
		}
	}

	public static function purgeStale( $older_than = 2592000 ) {
		$now  = time();
		$last = (int) get_option( self::PURGE_STAMP, 0 );

		if ( $last && $now - $last < DAY_IN_SECONDS ) {
			return 0;
		}

		update_option( self::PURGE_STAMP, $now, false );

		$wpdb = $GLOBALS['wpdb'];

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT %d",
				$wpdb->esc_like( self::ROW_PREFIX ) . '%',
				self::PURGE_BATCH
			)
		);

		$deleted = 0;
		foreach ( (array) $rows as $row ) {
			$record = maybe_unserialize( $row->option_value );
			$stamp  = is_array( $record ) && isset( $record['last'] ) ? (int) $record['last'] : 0;

			if ( $stamp && $now - $stamp > $older_than ) {
				delete_option( $row->option_name );
				$deleted ++;
			}
		}

		return $deleted;
	}

	private static function rowName( $tp_id ) {
		return self::ROW_PREFIX . (int) $tp_id;
	}

}
