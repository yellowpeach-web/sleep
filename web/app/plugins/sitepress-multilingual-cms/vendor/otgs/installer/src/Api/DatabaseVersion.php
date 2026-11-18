<?php

namespace OTGS\Installer\Api;

class DatabaseVersion {

	public static function get() {
		global $wpdb;

		if ( ! is_object( $wpdb ) ) {
			return '';
		}

		foreach ( [ 'db_server_info', 'db_version' ] as $method ) {
			if ( ! method_exists( $wpdb, $method ) ) {
				continue;
			}
			try {
				$version = $wpdb->$method();
			} catch ( \Exception $e ) {
				continue;
			} catch ( \Throwable $e ) {
				continue;
			}
			if ( is_scalar( $version ) && '' !== (string) $version ) {
				return (string) $version;
			}
		}

		return '';
	}
}
