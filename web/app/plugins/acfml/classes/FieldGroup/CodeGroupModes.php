<?php

namespace ACFML\FieldGroup;

use ACFML\Options;
use WPML\FP\Fns;
use WPML\FP\Obj;

class CodeGroupModes {

	const OPTION_KEY = 'code-field-group-modes';

	public function getForUnconfigured( array $fieldGroup ) {
		if ( self::isStoredInDatabase( $fieldGroup ) ) {
			return self::getStoredMode( $fieldGroup );
		}

		if ( ! self::isLocal( $fieldGroup ) ) {
			return Mode::TRANSLATION;
		}

		$storedModes = Options::get( self::OPTION_KEY );

		if ( ! is_array( $storedModes ) ) {
			return Mode::ADVANCED;
		}

		$key = (string) Obj::prop( 'key', $fieldGroup );

		if ( ! isset( $storedModes[ $key ] ) ) {
			$storedModes[ $key ] = Mode::TRANSLATION;
			Options::set( self::OPTION_KEY, $storedModes );
		}

		return $storedModes[ $key ];
	}

	public function rememberLocalGroups( $isExistingSite ) {
		if ( is_array( Options::get( self::OPTION_KEY ) ) ) {
			return;
		}

		$expertModes = $isExistingSite
			? wpml_collect( acf_get_local_field_groups() )
				->filter( [ Mode::class, 'isAdvanced' ] )
				->keyBy( 'key' )
				->map( Fns::always( Mode::ADVANCED ) )
				->toArray()
			: [];

		Options::set( self::OPTION_KEY, $expertModes );
	}

	private static function isLocal( array $fieldGroup ) {
		return (bool) Obj::prop( 'local', $fieldGroup ) && (bool) Obj::prop( 'key', $fieldGroup );
	}

	private static function getStoredMode( array $fieldGroup ) {
		return Mode::getModeOrDefault( acf_get_field_group( (int) Obj::prop( 'ID', $fieldGroup ) ) ?: [] );
	}

	private static function isStoredInDatabase( array $fieldGroup ) {
		return (int) Obj::prop( 'ID', $fieldGroup ) > 0;
	}
}
