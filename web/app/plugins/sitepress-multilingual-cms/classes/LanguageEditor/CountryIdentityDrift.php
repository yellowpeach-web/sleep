<?php

namespace WPML\LanguageEditor;

use WPML\LanguageEditor\Adapter\LanguageRepository;

final class CountryIdentityDrift {

	public static function divergedLanguages( ?LanguageRepository $languages = null, ?callable $labels = null ) {
		global $wpdb;

		$languages = $languages ? $languages : new LanguageRepository();
		$table     = $wpdb->prefix . 'icl_languages';

		$rows = (array) $wpdb->get_results(
			"SELECT english_name, code, country, default_locale FROM `{$table}`
			 WHERE active = 1 AND country IS NOT NULL AND country <> ''
			 ORDER BY english_name ASC"
		);

		$diverged = [];

		foreach ( $rows as $row ) {
			$code = isset( $row->code ) ? (string) $row->code : '';
			if ( '' === $code ) {
				continue;
			}

			$resolved = LanguageCodeResolution::resolve( $code );
			$preset   = null !== $resolved ? (string) $resolved['preset_code'] : '';
			if ( '' === $preset ) {
				continue;
			}

			$pair = $languages->presetPair( $preset, (string) $row->country, true );

			$expected = is_array( $pair ) && isset( $pair['default_locale'] )
				? trim( (string) $pair['default_locale'] )
				: '';
			$stored   = isset( $row->default_locale ) ? trim( (string) $row->default_locale ) : '';

			if ( ! self::localeDiverged( $stored, $expected ) ) {
				continue;
			}

			$diverged[] = [
				'code'     => $code,
				'name'     => isset( $row->english_name ) ? (string) $row->english_name : $code,
				'locale'   => $stored,
				'expected' => $expected,
			];
		}

		return self::withEditorLabels( $diverged, $labels );
	}

	private static function withEditorLabels( array $diverged, $labels = null ) {
		if ( [] === $diverged ) {
			return $diverged;
		}

		$map = null === $labels ? self::editorLabels() : (array) call_user_func( $labels );

		foreach ( $diverged as $index => $row ) {
			$code                        = isset( $row['code'] ) ? (string) $row['code'] : '';
			$label                       = isset( $map[ $code ] ) ? trim( (string) $map[ $code ] ) : '';
			$diverged[ $index ]['label'] = '' !== $label ? $label : (string) $row['name'];
		}

		return $diverged;
	}

	private static function editorLabels() {
		try {
			$rows = (array) PageData::active();
		} catch ( \Throwable $e ) {
			return [];
		}

		$labels = [];
		foreach ( $rows as $active ) {
			if ( ! is_array( $active ) ) {
				continue;
			}
			$code = isset( $active['code'] ) ? (string) $active['code'] : '';
			$name = isset( $active['localizedName'] ) ? trim( (string) $active['localizedName'] ) : '';
			if ( '' !== $code && '' !== $name ) {
				$labels[ $code ] = $name;
			}
		}

		return $labels;
	}

	public static function localeDiverged( $stored, $expected ) {
		$stored   = trim( (string) $stored );
		$expected = trim( (string) $expected );

		if ( '' === $expected ) {
			return false;
		}

		return 0 !== strcasecmp( $stored, $expected );
	}
}
