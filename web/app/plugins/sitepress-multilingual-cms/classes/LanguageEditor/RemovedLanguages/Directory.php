<?php

namespace WPML\LanguageEditor\RemovedLanguages;

use WPML\LanguageEditor\LanguageCodeResolution;
use WPML\LanguageEditor\Save\Phase\RemovalRecord;
use WPML\OperationRecord\Repository;
use WPML\Posts\TranslatedContentOfLanguages;
use WPML\Troubleshooting\OrphanedTranslations;

class Directory {

	public static function all() {
		$inactive = OrphanedTranslations::inactiveCodes();

		if ( ! $inactive ) {
			return array();
		}

		$totals = TranslatedContentOfLanguages::totalsByLanguage( $inactive );

		$codes = array();
		foreach ( $inactive as $code ) {
			$code = (string) $code;
			if ( isset( $totals[ $code ] ) && (int) $totals[ $code ] > 0 ) {
				$codes[] = $code;
			}
		}

		if ( ! $codes ) {
			return array();
		}

		$names = DisplayNames::forCodes( $codes );

		$stored       = self::storedRows( $codes );
		$displayCodes = array();
		$countries    = array();
		foreach ( $stored as $storedCode => $storedRow ) {
			$displayCodes[ $storedCode ] = $storedRow['display_code'];
			$countries[ $storedCode ]    = $storedRow['country'];
		}

		$repository = new Repository();
		$rows       = array();

		foreach ( $codes as $code ) {
			$counts = TranslatedContentOfLanguages::counts( array( $code ) );

			$records = $repository->forLanguage( $code );

			$displayCode = isset( $displayCodes[ $code ] ) && '' !== $displayCodes[ $code ]
				? $displayCodes[ $code ]
				: $code;

			$rows[] = array(
				'code'        => $code,
				'displayCode' => $displayCode,
				'name'    => isset( $names[ $code ] ) && $names[ $code ] !== $code ? $names[ $code ] : $displayCode,
				'country'    => isset( $countries[ $code ] ) && '' !== $countries[ $code ] ? $countries[ $code ] : null,
				'presetCode' => self::presetCodeOf( $code ),
				'isCustom'   => self::isCustomOf( $code ),
				'total'   => (int) $counts['total'],
				'types'   => $counts['types'],
				'targets' => MoveService::targetsFor( $code ),
				'removed' => self::removedAt( $records ),
				'record'  => self::summary( $records ),
			);
		}

		return $rows;
	}

	public static function displayCodes( array $codes ) {
		$out = array();
		foreach ( self::storedRows( $codes ) as $code => $row ) {
			$out[ $code ] = $row['display_code'];
		}

		return $out;
	}

	private static function storedRows( array $codes ) {
		global $wpdb;

		$out = array();
		foreach ( $codes as $code ) {
			$out[ (string) $code ] = array(
				'display_code' => '',
				'country'      => '',
			);
		}

		if ( ! $out || ! is_object( $wpdb ) ) {
			return $out;
		}

		$hasDisplayCode = (bool) $wpdb->get_var(
			$wpdb->prepare( "SHOW COLUMNS FROM `{$wpdb->prefix}icl_languages` LIKE %s", 'display_code' )
		);

		$hasCountry = (bool) $wpdb->get_var(
			$wpdb->prepare( "SHOW COLUMNS FROM `{$wpdb->prefix}icl_languages` LIKE %s", 'country' )
		);

		$rows = array();
		if ( $hasDisplayCode || $hasCountry ) {
			$columns      = 'code' . ( $hasDisplayCode ? ', display_code' : '' ) . ( $hasCountry ? ', country' : '' );
			$list         = array_keys( $out );
			$placeholders = implode( ',', array_fill( 0, count( $list ), '%s' ) );

			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT {$columns} FROM {$wpdb->prefix}icl_languages WHERE code IN ({$placeholders})",
					...$list
				)
			);
		}

		foreach ( (array) $rows as $row ) {
			if ( ! isset( $row->code ) ) {
				continue;
			}

			$code = (string) $row->code;
			if ( ! array_key_exists( $code, $out ) ) {
				continue;
			}

			if ( isset( $row->display_code ) ) {
				$out[ $code ]['display_code'] = (string) $row->display_code;
			}

			if ( isset( $row->country ) ) {
				$out[ $code ]['country'] = strtoupper( trim( (string) $row->country ) );
			}
		}

		return $out;
	}

	private static function isCustomOf( $code ) {
		if ( ! class_exists( '\WPML\LanguageEditor\LanguageCodeResolution' ) ) {
			return false;
		}

		return LanguageCodeResolution::isCustomIdentity( (string) $code );
	}

	private static function presetCodeOf( $code ) {
		if ( ! class_exists( '\WPML\LanguageEditor\LanguageCodeResolution' ) ) {
			return null;
		}

		$resolved = LanguageCodeResolution::resolve( (string) $code );

		return null !== $resolved && '' !== (string) $resolved['preset_code']
			? (string) $resolved['preset_code']
			: null;
	}

	private static function removedAt( array $records ) {
		foreach ( $records as $record ) {
			$kind = isset( $record['kind'] ) ? (string) $record['kind'] : '';

			if ( Repository::KIND_LANGUAGE_REMOVAL === $kind && isset( $record['started'] ) ) {
				return (int) $record['started'];
			}
		}

		return null;
	}

	private static function summary( array $records ) {
		if ( ! $records ) {
			return null;
		}

		$record = $records[0];

		return array_merge(
			RemovalRecord::summary( $record ),
			array(
				'kind'     => isset( $record['kind'] ) ? (string) $record['kind'] : '',
				'started'  => isset( $record['started'] ) ? (int) $record['started'] : 0,
				'finished' => isset( $record['finished'] ) && null !== $record['finished'] ? (int) $record['finished'] : null,
			)
		);
	}
}
