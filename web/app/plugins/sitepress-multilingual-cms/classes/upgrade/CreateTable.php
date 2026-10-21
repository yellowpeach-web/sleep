<?php

namespace WPML\Upgrade;

class CreateTable {

	const OPTION = 'wpml_create_table_refusals';

	public static function charsetCollate() {
		return (string) \SitePress_Setup::get_charset_collate();
	}

	public static function create( $wpdb, $table, $statement ) {
		$query = rtrim( (string) $statement ) . ' ' . self::charsetCollate();

		if ( false === $wpdb->query( $query ) ) {
			self::recordRefusal( $table, $query, isset( $wpdb->last_error ) ? (string) $wpdb->last_error : '' );

			return false;
		}

		return true;
	}

	public static function refusals() {
		if ( ! array_key_exists( self::OPTION, (array) wp_load_alloptions() ) ) {
			return [];
		}

		return self::readRecord();
	}

	public static function forgetCreatedTables( $wpdb ) {
		$records = self::refusals();
		if ( ! $records ) {
			return [];
		}

		$remaining = [];
		foreach ( $records as $table => $record ) {
			$found = (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
			if ( 0 !== strcasecmp( $found, (string) $table ) ) {
				$remaining[ $table ] = $record;
			}
		}

		if ( ! $remaining ) {
			delete_option( self::OPTION );
		} elseif ( count( $remaining ) !== count( $records ) ) {
			update_option( self::OPTION, $remaining, true );
		}

		return $remaining;
	}

	private static function recordRefusal( $table, $query, $error ) {
		$records = self::readRecord();

		$known = isset( $records[ $table ] ) ? $records[ $table ] : null;
		if ( $known && $known['query'] === $query && $known['error'] === $error ) {
			return;
		}

		$records[ $table ] = [
			'query' => $query,
			'error' => $error,
			'time'  => $known ? $known['time'] : time(),
		];

		update_option( self::OPTION, $records, true );
	}

	private static function readRecord() {
		$stored  = get_option( self::OPTION, [] );
		$records = [];

		foreach ( is_array( $stored ) ? $stored : [] as $table => $record ) {
			if ( is_string( $table ) && is_array( $record ) ) {
				$records[ $table ] = [
					'query' => isset( $record['query'] ) ? (string) $record['query'] : '',
					'error' => isset( $record['error'] ) ? (string) $record['error'] : '',
					'time'  => isset( $record['time'] ) ? (int) $record['time'] : 0,
				];
			}
		}

		return $records;
	}
}
