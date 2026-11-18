<?php

namespace ACFML\FieldPreferences;

use WPML\TM\Settings\PreferenceResolver;
use WPML\TM\Settings\PreferenceWriter;

class ModeWrites {

	const ELEMENT_TYPES = [
		'custom_fields_translation'      => 'post',
		'custom_term_fields_translation' => 'term',
	];

	public static function save( $tm, array $modesByIndex ) {
		$modesByIndex = array_filter( $modesByIndex );
		if ( ! $modesByIndex ) {
			return;
		}

		if ( self::canWritePerKey() ) {
			foreach ( $modesByIndex as $index => $modes ) {
				PreferenceWriter::setModes( self::ELEMENT_TYPES[ $index ], $modes );
			}

			return;
		}

		foreach ( $modesByIndex as $index => $modes ) {
			foreach ( $modes as $name => $mode ) {
				$tm->settings[ $index ][ $name ] = $mode;
			}
		}
		$tm->save_settings();
	}

	public static function canWritePerKey(): bool {
		return class_exists( PreferenceWriter::class );
	}

	public static function storedModes( $tm, $index, array $names ) {
		$names = array_map( 'strval', $names );

		if ( self::canReadPerKey() ) {
			return PreferenceResolver::modes( self::ELEMENT_TYPES[ $index ], $names );
		}

		$map   = isset( $tm->settings[ $index ] ) && is_array( $tm->settings[ $index ] ) ? $tm->settings[ $index ] : [];
		$modes = [];
		foreach ( $names as $name ) {
			$modes[ $name ] = isset( $map[ $name ] ) ? (int) $map[ $name ] : null;
		}

		return $modes;
	}

	public static function canReadPerKey(): bool {
		return class_exists( PreferenceResolver::class );
	}
}
