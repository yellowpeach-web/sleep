<?php

namespace WPML\LanguageEditor\Save\Phase;

use WPML\OperationRecord\Repository;

class RemovalRecord {

	public static function ensure( array $codes, $route ) {
		$running = self::running( $codes );

		if ( $running ) {
			return (int) $running['id'];
		}

		return ( new Repository() )->start( Repository::KIND_LANGUAGE_REMOVAL, $codes, null, $route );
	}

	public static function running( array $codes ) {
		foreach ( self::forCodes( $codes ) as $record ) {
			if ( Repository::STATUS_RUNNING === ( isset( $record['status'] ) ? $record['status'] : '' ) ) {
				return $record;
			}
		}

		return null;
	}

	public static function latest( array $codes ) {
		$found = self::forCodes( $codes );

		return $found ? $found[0] : null;
	}

	public static function summary( array $record ) {
		return array(
			'id'               => isset( $record['id'] ) ? (int) $record['id'] : 0,
			'counts'           => isset( $record['counts'] ) ? (array) $record['counts'] : array(),
			'jobs'             => isset( $record['jobs'] ) && is_array( $record['jobs'] ) ? $record['jobs'] : null,
			'route'            => isset( $record['route'] ) ? $record['route'] : null,
			'undo_until'       => isset( $record['undo_until'] ) && null !== $record['undo_until']
				? (int) $record['undo_until']
				: null,
			'status'           => isset( $record['status'] ) ? (string) $record['status'] : Repository::STATUS_RUNNING,
			'skipped'          => isset( $record['skipped'] ) ? (array) $record['skipped'] : array(),
			'skipped_overflow' => isset( $record['skipped_overflow'] ) ? (int) $record['skipped_overflow'] : 0,
		);
	}

	private static function forCodes( array $codes ) {
		$codes = array_values( array_map( 'strval', $codes ) );

		if ( ! $codes ) {
			return array();
		}

		$wanted = $codes;
		sort( $wanted );

		$found = array();

		foreach ( ( new Repository() )->forLanguage( $codes[0] ) as $record ) {
			if ( Repository::KIND_LANGUAGE_REMOVAL !== ( isset( $record['kind'] ) ? $record['kind'] : '' ) ) {
				continue;
			}

			$languages = isset( $record['languages'] ) ? array_map( 'strval', (array) $record['languages'] ) : array();
			sort( $languages );

			if ( $languages === $wanted ) {
				$found[] = $record;
			}
		}

		return $found;
	}
}
