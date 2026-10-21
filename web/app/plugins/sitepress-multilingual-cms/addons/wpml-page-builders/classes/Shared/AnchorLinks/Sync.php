<?php

namespace WPML\PB\AnchorLinks;

class Sync {

	public const ANCHOR_TYPE = 'LINE';
	public const LINK_TYPE   = 'LINK';

	private const ANCHOR_NAME_PATTERNS = [
		'/^anchor-menu-anchor-/',
	];

	public static function run( array $translations, $package, string $lang ): array {
		$sourceStrings = self::indexSourceStrings( $package );
		$anchors       = self::localisedAnchorsByValue( $translations, $sourceStrings, $lang );

		if ( ! $anchors ) {
			return $translations;
		}

		foreach ( $sourceStrings as $name => $source ) {
			if ( self::LINK_TYPE !== $source['type'] ) {
				continue;
			}

			$target = self::anchorTarget( $source['value'] );

			if ( null === $target
				|| ! isset( $anchors[ $target ] )
				|| ! self::isUntouchedLink( $translations, $name, $lang, $source['value'] )
			) {
				continue;
			}

			$anchor                         = $anchors[ $target ];
			$translations[ $name ][ $lang ] = array_merge(
				$anchor['translation'],
				[ 'value' => '#' . sanitize_html_class( $anchor['value'] ) ]
			);
		}

		return $translations;
	}

	private static function indexSourceStrings( $package ): array {
		$sourceStrings = [];

		foreach ( (array) $package->get_package_strings() as $string ) {
			$sourceStrings[ $string->name ] = [
				'value' => $string->value,
				'type'  => $string->type,
			];
		}

		return $sourceStrings;
	}

	private static function localisedAnchorsByValue( array $translations, array $sourceStrings, string $lang ): array {
		$anchors = [];

		$patterns = apply_filters( 'wpml_pb_anchor_string_name_patterns', self::ANCHOR_NAME_PATTERNS );

		foreach ( $sourceStrings as $name => $source ) {
			if ( self::ANCHOR_TYPE !== $source['type']
				|| ! self::isAnchorName( (string) $name, $patterns )
				|| '' === (string) $source['value']
				|| ! isset( $translations[ $name ][ $lang ]['value'] )
			) {
				continue;
			}

			$value       = $source['value'];
			$translation = $translations[ $name ][ $lang ];

			if ( $translation['value'] === $value ) {
				continue;
			}

			if ( ! isset( $anchors[ $value ] ) ) {
				$anchors[ $value ] = [
					'value'       => $translation['value'],
					'translation' => $translation,
				];
			} elseif ( false !== $anchors[ $value ] && $anchors[ $value ]['value'] !== $translation['value'] ) {
				$anchors[ $value ] = false;
			}
		}

		return array_filter( $anchors );
	}

	private static function isAnchorName( string $name, array $patterns ): bool {
		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $name ) ) {
				return true;
			}
		}

		return false;
	}

	private static function anchorTarget( $value ): ?string {
		if ( ! is_string( $value ) || '#' !== substr( $value, 0, 1 ) ) {
			return null;
		}

		$target = substr( $value, 1 );

		if ( '' === $target || false !== strpos( $target, '#' ) ) {
			return null;
		}

		return $target;
	}

	private static function isUntouchedLink( array $translations, $name, string $lang, $source ): bool {
		return ! isset( $translations[ $name ][ $lang ]['value'] )
				|| $translations[ $name ][ $lang ]['value'] === $source;
	}
}
