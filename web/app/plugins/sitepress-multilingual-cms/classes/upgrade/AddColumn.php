<?php

namespace WPML\Upgrade;

class AddColumn {

	const OPTION = 'wpml_add_column_refusals';

	const MAX_ATTEMPTS = 3;

	const ZERO_DATE_MODES = [ 'NO_ZERO_DATE', 'NO_ZERO_IN_DATE', 'TRADITIONAL' ];

	public static function run( $wpdb, $table, $column, callable $alter ) {
		if ( self::gaveUp( $table, $column ) ) {
			return false;
		}

		$key     = $table . '.' . $column;
		$records = self::refusals();

		$result = $alter();

		if ( false === $result && self::isZeroDateRefusal( $wpdb->last_error, $column ) ) {
			$refusal = (string) $wpdb->last_error;
			$result  = self::runWithoutZeroDateModes( $wpdb, $alter );

			if ( false !== $result ) {
				\WPML\PHP\Logger\notice(
					'WPML added ' . $key . ' after a zero-date refusal ("' . $refusal . '"): '
					. 'it ran once more without the zero-date session modes, which are restored.'
				);
			}
		}

		if ( false === $result ) {
			$error = (string) $wpdb->last_error;
			$query = (string) $wpdb->last_query;
			self::recordRefusal( $records, $key, $table, $column, $query, $error );
			$wpdb->last_error = $error;
			$wpdb->last_query = $query;
		}

		return $result;
	}

	public static function gaveUp( $table, $column ) {
		$records = self::refusals();
		$key     = $table . '.' . $column;

		return isset( $records[ $key ] ) && $records[ $key ]['attempts'] >= self::MAX_ATTEMPTS;
	}

	public static function refusals() {
		if ( ! array_key_exists( self::OPTION, (array) wp_load_alloptions() ) ) {
			return [];
		}

		return self::readRecord();
	}

	public static function forgetAddedColumns( $wpdb ) {
		$records = self::refusals();
		if ( ! $records ) {
			return [];
		}

		$remaining = [];
		foreach ( $records as $key => $record ) {
			if ( ! self::columnExists( $wpdb, $record['table'], $record['column'] ) ) {
				$remaining[ $key ] = $record;
			}
		}

		if ( ! $remaining ) {
			delete_option( self::OPTION );
		} elseif ( count( $remaining ) !== count( $records ) ) {
			update_option( self::OPTION, $remaining, true );
		}

		return $remaining;
	}

	public static function retry() {
		$records = self::refusals();
		$changed = false;

		foreach ( $records as $key => $record ) {
			if ( $record['attempts'] >= self::MAX_ATTEMPTS ) {
				$records[ $key ]['attempts'] = self::MAX_ATTEMPTS - 1;
				$changed                     = true;
			}
		}

		if ( $changed ) {
			update_option( self::OPTION, $records, true );
		}
	}

	private static function isZeroDateRefusal( $error, $column ) {
		$error = (string) $error;

		if ( preg_match( "/^Incorrect (datetime|date) value: '0000-00-00/", $error ) ) {
			return true;
		}

		return (bool) preg_match( "/^Invalid default value for '([^']+)'/", $error, $match )
			&& $match[1] !== $column;
	}

	private static function runWithoutZeroDateModes( $wpdb, callable $alter ) {
		$refusal = (string) $wpdb->last_error;
		$refused = (string) $wpdb->last_query;

		$modes = (string) $wpdb->get_var( 'SELECT @@SESSION.sql_mode' );
		if ( '' === $modes ) {
			$wpdb->last_error = $refusal;
			$wpdb->last_query = $refused;

			return false;
		}

		$relaxed = implode( ',', array_diff( explode( ',', $modes ), self::ZERO_DATE_MODES ) );
		$wpdb->query( $wpdb->prepare( 'SET SESSION sql_mode = %s', $relaxed ) );

		try {
			$result = $alter();
			$error  = (string) $wpdb->last_error;
			$query  = (string) $wpdb->last_query;
		} finally {
			$wpdb->query( $wpdb->prepare( 'SET SESSION sql_mode = %s', $modes ) );
		}

		$wpdb->last_error = $error;
		$wpdb->last_query = $query;

		return $result;
	}

	private static function columnExists( $wpdb, $table, $column ) {
		if ( ! preg_match( '/^[A-Za-z0-9_]+$/', $table ) ) {
			return false;
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare( 'SHOW COLUMNS FROM `' . esc_sql( $table ) . '` LIKE %s', $column )
		);

		return is_array( $rows ) && count( $rows ) > 0;
	}

	private static function recordRefusal( array $records, $key, $table, $column, $query, $error ) {
		$known = isset( $records[ $key ] ) ? $records[ $key ] : null;

		$records[ $key ] = [
			'table'    => $table,
			'column'   => $column,
			'query'    => $query,
			'error'    => $error,
			'time'     => $known ? $known['time'] : time(),
			'attempts' => ( $known ? $known['attempts'] : 0 ) + 1,
		];

		update_option( self::OPTION, $records, true );
	}

	private static function readRecord() {
		$stored  = get_option( self::OPTION, [] );
		$records = [];

		foreach ( is_array( $stored ) ? $stored : [] as $key => $record ) {
			if ( is_string( $key ) && is_array( $record ) ) {
				$records[ $key ] = [
					'table'    => isset( $record['table'] ) ? (string) $record['table'] : '',
					'column'   => isset( $record['column'] ) ? (string) $record['column'] : '',
					'query'    => isset( $record['query'] ) ? (string) $record['query'] : '',
					'error'    => isset( $record['error'] ) ? (string) $record['error'] : '',
					'time'     => isset( $record['time'] ) ? (int) $record['time'] : 0,
					'attempts' => isset( $record['attempts'] ) ? (int) $record['attempts'] : 0,
				];
			}
		}

		return $records;
	}
}
