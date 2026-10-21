<?php

namespace ACFML\Repeater\Shuffle;

use ACFML\Options\StorageLanguage;
use WPML\Collect\Support\Collection;
use WPML\FP\Fns;
use WPML\FP\Lst;
use WPML\FP\Str;
use WPML\FP\Obj;
use function WPML\FP\curryN;

class OptionsPage extends Strategy {
	protected $valid_ids = null;

	public function getEntityType() {
		return 'option';
	}

	public function isValidId( $id ) {
		$starting_with_option_id = Fns::unary( Str::startsWith( Fns::__, $id ) );

		return (bool) $this->getValidOptionsPagesIds()
				->first( $starting_with_option_id );
	}

	private function getValidOptionsPagesIds() {
		if ( null === $this->valid_ids ) {
			$this->valid_ids = wpml_collect( Lst::pluck( 'post_id', acf_get_options_pages() ) );
		}

		return $this->valid_ids;
	}

	protected function getElement( $id ) {
		return null;
	}

	protected function get_element_type( $id = null ) {
		return '';
	}

	public function getAllMeta( $id ) {
		$options = [];
		$fields  = get_fields( $id );
		$fields  = $fields ? $fields : [];
		foreach ( $fields as $key => $value ) {
			$options = $this->addNormalizedValuesForFieldState( $options, $key, $value );
		}
		return $options;
	}

	private function addNormalizedValuesForFieldState( $options, $prefixedKey, $value ) {
		if ( $value instanceof \WP_Post || ( is_array( $value ) && isset( $value['ID'] ) ) ) {
			return array_merge( $options, [ $prefixedKey => Obj::prop( 'ID', $value ) ] );
		} elseif ( $value instanceof \WP_Term ) {
			return array_merge( $options, [ $prefixedKey => Obj::prop( 'term_id', $value ) ] );
		} elseif ( $this->isArrayOfStringsOrArrayOfIntegers( $value ) ) {
			return array_merge( $options, [ $prefixedKey => $value ] );
		} elseif ( is_array( $value ) ) {
			foreach ( $value as $index => $item ) {
				if ( is_numeric( $index ) ) {
					foreach ( $item as $field => $field_value ) {
						$options = array_merge( $options, $this->addNormalizedValuesForFieldState( $options, $prefixedKey . '_' . $index . '_' . $field, $field_value ) );
					}
				} else {
					$options = $this->addNormalizedValuesForFieldState( $options, $prefixedKey . '_' . $index, $item );
				}
			}
			return $options;
		} else {
			return array_merge( $options, [ $prefixedKey => $value ] );
		}
	}

	private function isArrayOfStringsOrArrayOfIntegers( $value ) {
		$intIndexTypeValue = curryN( 3, function( $typeCheck, $value, $index ) {
			return is_int( $index ) && $typeCheck( $value );
		} );

		return is_array( $value ) && (
			count( $value ) === wpml_collect( $value )->filter( $intIndexTypeValue( 'is_string' ) )->count() ||
			count( $value ) === wpml_collect( $value )->filter( $intIndexTypeValue( 'is_int' ) )->count()
		);
	}

	public function readOneValue( $entry ) {
		return $entry;
	}

	public function getOneMeta( $id, $key, $single = true ) {
		return get_option( $this->getOptionName( $id, $key ) );
	}

	public function deleteOneMeta( $id, $key ) {
		delete_option( $this->getOptionName( $id, $key ) );
	}

	public function updateOneMeta( $id, $key, $val ) {
		update_option( $this->getOptionName( $id, $key ), $val, $this->isAutoloaded( $id ) );
	}

	private function isAutoloaded( $id ) {
		$optionsPage = wpml_collect( acf_get_options_pages() ?: [] )
			->filter( fn( $page ) => Str::startsWith( (string) Obj::prop( 'post_id', $page ), $id ) )
			->sortByDesc( fn( $page ) => strlen( (string) Obj::prop( 'post_id', $page ) ) )
			->first();

		return (bool) Obj::prop( 'autoload', $optionsPage );
	}

	private function getOptionName( $id, $key ) {
		return $id . '_' . $key;
	}

	public function getTrid( $elementId ) {
		$storageLanguage = $this->getStorageLanguage();
		$currentLanguage = apply_filters( 'wpml_current_language', null );
		if ( $currentLanguage === $storageLanguage ) {
			return $elementId;
		}
		$suffix = '_' . $currentLanguage;

		return Str::endsWith( $suffix, $elementId ) ? substr( $elementId, 0, -strlen( $suffix ) ) : $elementId;
	}

	public function getTranslations( $id ) {
		if ( ! isset( $this->element_translations[ $id ] ) ) {
			$activeLanguages = apply_filters( 'wpml_active_languages', null );
			$storageLanguage = $this->getStorageLanguage();
			$currentLanguage = apply_filters( 'wpml_current_language', null );

			$getOptionName = function ( $id, $languageCode ) use ( $storageLanguage ) {
				$optionName = $this->getTrid( $id );

				if ( $languageCode !== $storageLanguage ) {
					$optionName .= '_' . $languageCode;
				}

				return $optionName;
			};

			foreach ( $activeLanguages as $languageCode => $language ) {
				if ( $languageCode !== $currentLanguage ) {
					$this->element_translations[ $id ][ $languageCode ] = (object) [
						'element_id' => $getOptionName( $id, $languageCode ),
					];
				}
			}
		}

		return (array) Obj::prop( $id, $this->element_translations );
	}

	private function getStorageLanguage() {
		return apply_filters( StorageLanguage::ACF_DEFAULT_LANGUAGE_FILTER, apply_filters( 'wpml_default_language', null ) );
	}

	public function isOriginal( $id ) {
		$currentLanguages = apply_filters( 'wpml_current_language', null );
		$storageLanguage  = $this->getStorageLanguage();

		return $storageLanguage === $currentLanguages;
	}
}
