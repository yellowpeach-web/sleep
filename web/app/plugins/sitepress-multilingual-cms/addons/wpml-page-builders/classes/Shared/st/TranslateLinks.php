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

			$seedValue = function ( $language ) use ( $string, $string_id ) {
				$original = (string) $string->get_value();
				$data     = apply_filters( 'wpml_st_string_translation_before_save', [ 'value' => $original ], $language, $string_id );

				return is_array( $data ) && isset( $data['value'] ) ? (string) $data['value'] : $original;
			};

			$shouldKeepRow = function ( $language ) use ( $existingByLanguage, $seedValue ) {
				if ( ! isset( $existingByLanguage[ $language ] ) ) {
					return false;
				}

				$row    = $existingByLanguage[ $language ];
				$value  = (string) ( $row->value ?? '' );
				$status = (int) ( $row->status ?? ICL_TM_NOT_TRANSLATED );

				if ( '' === $value || (int) ICL_TM_NEEDS_UPDATE === $status ) {
					return false;
				}

				$isSeed = (int) ICL_TM_NOT_TRANSLATED === $status;

				return ! $isSeed || $seedValue( $language ) === $value;
			};

			$setTranslation = function ( $language ) use ( $string ) {
				$string->set_translation( $language, $string->get_value() );
			};

			\wpml_collect( $activeLanguages )->pluck( 'code' )
			                                 ->reject( $sameStringLanguage )
			                                 ->reject( $shouldKeepRow )
			                                 ->each( $setTranslation );
		};
	}

}
