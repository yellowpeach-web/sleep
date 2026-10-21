<?php

namespace WPML\LanguageEditor;

use WPML\Element\API\Languages;

class Labels {

	public function get( $code, array $targetCodes, $fallback = '' ) {
		$saved   = $this->getSaved( $code );
		$builtIn = $this->getBuiltIn( $code );
		$miss    = '' !== trim( (string) $fallback ) ? (string) $fallback : $this->fallbackName( $code );

		$labels = [];
		foreach ( $targetCodes as $target ) {
			if ( isset( $saved[ $target ] ) && '' !== trim( (string) $saved[ $target ] ) ) {
				$labels[ $target ] = $saved[ $target ];
			} elseif ( isset( $builtIn[ $target ] ) && '' !== trim( (string) $builtIn[ $target ] ) ) {
				$labels[ $target ] = $builtIn[ $target ];
			} else {
				$labels[ $target ] = $miss;
			}
		}

		return $labels;
	}

	public function getDerived( $code, array $targetCodes ) {
		$builtIn = $this->getBuiltIn( $code );

		$labels = [];
		foreach ( $targetCodes as $target ) {
			$labels[ $target ] = isset( $builtIn[ $target ] ) && '' !== trim( (string) $builtIn[ $target ] )
				? $builtIn[ $target ]
				: $this->fallbackName( $code );
		}

		return $labels;
	}

	public function save( $code, array $labels ) {
		$saved = 0;
		foreach ( $labels as $displayCode => $name ) {
			$displayCode = (string) $displayCode;
			$name        = trim( (string) $name );
			if ( '' === $displayCode || '' === $name ) {
				continue;
			}
			if ( false !== Languages::setLanguageTranslation( $code, $displayCode, $name ) ) {
				$saved ++;
			}
		}

		return $saved;
	}

	private function getSaved( $code ) {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT display_language_code, name
				 FROM {$wpdb->prefix}icl_languages_translations
				 WHERE language_code = %s",
				$code
			),
			ARRAY_A
		);

		$out = [];
		foreach ( (array) $rows as $row ) {
			$out[ $row['display_language_code'] ] = $row['name'];
		}

		return $out;
	}

	private function getBuiltIn( $code ) {
		$names = icl_get_languages_names();
		$codes = icl_get_languages_codes();

		$sourceName = array_search( $code, $codes, true );
		if ( false === $sourceName || ! isset( $names[ $sourceName ]['tr'] ) ) {
			return [];
		}

		$out = [];
		foreach ( $names[ $sourceName ]['tr'] as $displayName => $localized ) {
			if ( 0 === strpos( $displayName, 'Norwegian Bokm' ) ) {
				$displayName = 'Norwegian Bokmål';
			}
			if ( isset( $codes[ $displayName ] ) && '' !== trim( (string) $localized ) ) {
				$out[ $codes[ $displayName ] ] = $localized;
			}
		}

		return $out;
	}

	private function fallbackName( $code ) {
		$codes = icl_get_languages_codes();
		$name  = array_search( $code, $codes, true );
		if ( false !== $name ) {
			return $name;
		}

		$preset = ActiveLanguages::preset( $code );
		if ( $preset && isset( $preset->english_name ) && '' !== trim( (string) $preset->english_name ) ) {
			return trim( (string) $preset->english_name );
		}

		return $code;
	}
}
