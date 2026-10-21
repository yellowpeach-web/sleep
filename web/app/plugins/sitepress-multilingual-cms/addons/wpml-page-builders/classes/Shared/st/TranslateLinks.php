<?php

namespace WPML\PB;

class TranslateLinks {

	public static function getTranslatorForString( \WPML_ST_String_Factory $stringFactory, $activeLanguages ) {
		return function ( $string_id ) use ( $stringFactory, $activeLanguages ) {
			$string = $stringFactory->find_by_id( $string_id );

			$existingByLanguage = [];
			foreach ( (array) $string->get_translations() as $row ) {
				if ( isset( $row->language ) ) {
					$existingByLanguage[ $row->language ] = $row;
				}
			}

			$sameStringLanguage = function ( $language ) use ( $string ) {
				return $language === $string->get_language();
			};

			$hasTranslatorValue = function ( $language ) use ( $existingByLanguage ) {
				if ( ! isset( $existingByLanguage[ $language ] ) ) {
					return false;
				}

				$row = $existingByLanguage[ $language ];

				return isset( $row->value ) && '' !== (string) $row->value
					&& (int) ICL_TM_NEEDS_UPDATE !== (int) ( isset( $row->status ) ? $row->status : 0 );
			};

			$setTranslation = function ( $language ) use ( $string ) {
				$string->set_translation( $language, $string->get_value() );
			};

			\wpml_collect( $activeLanguages )->pluck( 'code' )
			                                 ->reject( $sameStringLanguage )
			                                 ->reject( $hasTranslatorValue )
			                                 ->each( $setTranslation );
		};
	}

}
