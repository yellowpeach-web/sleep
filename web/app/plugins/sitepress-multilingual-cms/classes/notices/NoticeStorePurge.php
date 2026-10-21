<?php

namespace WPML\Notices;

use WPML\WP\OptionManager;

final class NoticeStorePurge {

	public static function removeEntries( \wpdb $wpdb, OptionManager $options, array $entries ) {
		$purged = [];

		foreach ( NoticeStores::rows( $wpdb ) as $option_name ) {
			if ( self::purgeRow( $options, $option_name, $entries ) ) {
				$purged[] = $option_name;
			}
		}

		return $purged;
	}

	private static function purgeRow( OptionManager $options, $option_name, array $entries ) {
		$stored = NoticeStoreRepair::readSafely( $option_name );

		if ( ! self::carries( $stored, $entries ) ) {
			return false;
		}

		$options->mutateRaw(
			$option_name,
			function ( $current ) use ( $entries ) {
				return self::without( is_array( $current ) ? $current : [], $entries );
			},
			false
		);

		return true;
	}

	private static function carries( array $stored, array $entries ) {
		foreach ( $entries as list( $group, $id ) ) {
			if ( isset( $stored[ $group ] ) && is_array( $stored[ $group ] )
				&& array_key_exists( $id, $stored[ $group ] ) ) {
				return true;
			}
		}

		return false;
	}

	private static function without( array $stored, array $entries ) {
		foreach ( $entries as list( $group, $id ) ) {
			if ( ! isset( $stored[ $group ] ) || ! is_array( $stored[ $group ] ) ) {
				continue;
			}

			unset( $stored[ $group ][ $id ] );

			if ( ! $stored[ $group ] ) {
				unset( $stored[ $group ] );
			}
		}

		return $stored;
	}
}
