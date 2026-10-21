<?php

namespace ACFML\FieldGroup;

use ACFML\Helper\Fields;
use WPML\FP\Fns;
use WPML\FP\Obj;
use WPML\FP\Relation;
use WPML\FP\Str;

class FieldNamePatterns {

	const OPTION_KEY = 'acfml_field_name_patterns';

	private $localPatterns = [];

	private $cachedMatches = [];

	private $cachedLocalMatches = [];

	public function updateFieldNamePatterns( $fieldGroup ) {
		$namePatterns = wpml_collect();

		if ( ! Mode::isAdvanced( $fieldGroup ) ) {
			$getFieldNamePattern = function( $field, $fieldPattern ) use ( $namePatterns ) {
				$namePatterns->push( $fieldPattern );

				return $field;
			};

			Fields::iterate( Fields::getFresh( $fieldGroup ), $getFieldNamePattern, Fns::identity() );
		}

		$this->updateGroup( $fieldGroup['key'], $namePatterns->toArray() );
	}

	public function updateGroup( $groupKey, $groupPatterns ) {
		$allPatterns = $this->getAllPatterns();

		if ( $groupPatterns ) {
			$allPatterns[ $groupKey ] = $groupPatterns;
		} else {
			unset( $allPatterns[ $groupKey ] );
		}

		update_option( self::OPTION_KEY, $allPatterns, false );
	}

	public function removeGroup( $groupKey ) {
		$this->updateGroup( $groupKey, [] );
	}

	public function findMatchingGroup( $fieldName ) {
		if ( array_key_exists( $fieldName, $this->cachedMatches ) ) {
			return $this->cachedMatches[ $fieldName ];
		}

		$this->cachedMatches[ $fieldName ] = null;

		foreach ( $this->getAllPatterns() as $groupKey => $patterns ) {
			if ( $this->matches( $fieldName, $patterns ) ) {
				$this->cachedMatches[ $fieldName ] = $groupKey;
				break;
			}
		}

		return $this->cachedMatches[ $fieldName ];
	}

	public function findMatchingLocalGroup( $fieldName, $source = 'json' ) {
		if ( ! array_key_exists( $source, $this->cachedLocalMatches ) ) {
			$this->cachedLocalMatches[ $source ] = [];
		}

		if ( array_key_exists( $fieldName, $this->cachedLocalMatches[ $source ] ) ) {
			return $this->cachedLocalMatches[ $source ][ $fieldName ];
		}

		$this->cachedLocalMatches[ $source ][ $fieldName ] = null;

		foreach ( $this->getAllLocalPatterns( $source ) as $groupKey => $patterns ) {
			if ( $this->matches( $fieldName, $patterns ) ) {
				$this->cachedLocalMatches[ $source ][ $fieldName ] = $groupKey;
				break;
			}
		}

		return $this->cachedLocalMatches[ $source ][ $fieldName ];
	}

	public function findMatchingValue( $fieldName, array $patternsToValue ) {
		foreach ( $patternsToValue as $pattern => $value ) {
			if ( $this->matchesPattern( $fieldName, $pattern ) ) {
				return $value;
			}
		}

		return null;
	}

	private function matches( $fieldName, $patterns ) {
		foreach ( $patterns as $pattern ) {
			if ( $this->matchesPattern( $fieldName, $pattern ) ) {
				return true;
			}
		}

		return false;
	}

	private function matchesPattern( $fieldName, $pattern ) {
		return (bool) Str::match( '#^' . $pattern . '$#', $fieldName );
	}

	public function getAllPatterns() {
		return (array) get_option( self::OPTION_KEY, [] );
	}

	private function buildLocalPatterns( $source = 'json' ) {
		$this->localPatterns[ $source ] = wpml_collect( acf_get_field_groups() )
			->filter( function( $fieldGroup ) use ( $source ) {
				if ( Mode::isAdvanced( $fieldGroup ) ) {
					return false;
				}
				if ( ! Relation::propEq( 'local', $source, $fieldGroup ) ) {
					return false;
				}
				if ( ! Relation::propEq( 'ID', 0, $fieldGroup ) ) {
					return false;
				}
				return true;
			} )
		->keyBy( 'key' )
		->map( function( $fieldGroup ) {
			$fieldGroupKey       = Obj::prop( 'key', $fieldGroup );
			$namePatterns        = wpml_collect();
			$getFieldNamePattern = function( $field, $fieldPattern ) use ( $namePatterns ) {
				$namePatterns->push( $fieldPattern );
				return $field;
			};
			Fields::iterate( acf_get_fields( $fieldGroupKey ), $getFieldNamePattern, Fns::identity() );
			return $namePatterns->filter()->toArray();
		} )
		->toArray();

		return $this->localPatterns[ $source ];
	}

	private function getAllLocalPatterns( $source = 'json' ) {
		if ( isset( $this->localPatterns[ $source ] ) ) {
			return $this->localPatterns[ $source ];
		}
		return $this->buildLocalPatterns( $source );
	}
}
