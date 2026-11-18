<?php

namespace WPML\PB\AnchorLinks;

class Sync {

	public const ANCHOR_TYPE = 'LINE';
	public const LINK_TYPE   = 'LINK';

	private const ANCHOR_NAME_PATTERNS = [
		'/^anchor-menu-anchor-/',
	];

	private const SHORTCODE_PACKAGE_KIND = 'Page Builder ShortCode Strings';

	private const ANCHOR_ATTRIBUTE_PATTERNS = [
		'/^el_id$/',
		'/^module_id$/',
		'/^menu_anchor$/',
	];

	public static function run( array $translations, $package, string $lang ): array {
		$sourceStrings = self::indexSourceStrings( $package );

		list( $anchors, $keptIds ) = self::localisedAnchorsByValue( $translations, $sourceStrings, $package, $lang );

		if ( ! $anchors ) {
			return $translations;
		}

		list( $translations, $anchors ) = self::keepSourceIdOfBrokenAnchors( $translations, $anchors, $keptIds, $lang );

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

	private static function localisedAnchorsByValue( array $translations, array $sourceStrings, $package, string $lang ): array {
		$anchors          = [];
		$keptIds          = [];
		$attributeAnchors = self::shortcodeAttributeAnchors( $package );

		$patterns = apply_filters( 'wpml_pb_anchor_string_name_patterns', self::ANCHOR_NAME_PATTERNS );

		foreach ( $sourceStrings as $name => $source ) {
			if ( self::ANCHOR_TYPE !== $source['type'] || '' === (string) $source['value'] ) {
				continue;
			}

			$value       = $source['value'];
			$translation = $translations[ $name ][ $lang ] ?? [ 'value' => $value ];

			if ( ! self::isAnchorName( (string) $name, $patterns )
				&& ! self::isAttributeAnchor( $attributeAnchors, $value, $translation['value'] )
			) {
				continue;
			}

			if ( $translation['value'] === $value ) {
				$keptIds[] = sanitize_html_class( $value );
				continue;
			}

			if ( ! isset( $anchors[ $value ] ) ) {
				$anchors[ $value ] = [
					'value'       => $translation['value'],
					'translation' => $translation,
					'names'       => [ (string) $name ],
				];
			} elseif ( false !== $anchors[ $value ] && $anchors[ $value ]['value'] !== $translation['value'] ) {
				$anchors[ $value ] = false;
			} elseif ( false !== $anchors[ $value ] ) {
				$anchors[ $value ]['names'][] = (string) $name;
			}
		}

		return [ array_filter( $anchors ), $keptIds ];
	}

	private static function keepSourceIdOfBrokenAnchors( array $translations, array $anchors, array $keptIds, string $lang ): array {
		$ids = array_map( fn( $anchor ) => sanitize_html_class( $anchor['value'] ), $anchors );

		do {
			$counts   = array_count_values( array_merge( $ids, $keptIds ) );
			$restored = false;

			foreach ( $ids as $sourceValue => $id ) {
				if ( '' !== $id && 1 === $counts[ $id ] ) {
					continue;
				}

				foreach ( $anchors[ $sourceValue ]['names'] as $name ) {
					$translations[ $name ][ $lang ]['value'] = (string) $sourceValue;
				}

				$keptIds[] = sanitize_html_class( (string) $sourceValue );
				unset( $anchors[ $sourceValue ], $ids[ $sourceValue ] );
				$restored = true;
			}
		} while ( $restored );

		return [ $translations, $anchors ];
	}

	private static function shortcodeAttributeAnchors( $package ): array {
		if ( ! isset( $package->kind, $package->post_id ) || self::SHORTCODE_PACKAGE_KIND !== $package->kind ) {
			return [];
		}

		$translatedAttributes = self::translatedShortcodeAttributes();

		if ( ! $translatedAttributes ) {
			return [];
		}

		$content = (string) get_post_field( 'post_content', (int) $package->post_id );

		if ( ! preg_match_all( '/\[([\w-]+)(\s[^\]]*)\]/', $content, $matches, PREG_SET_ORDER ) ) {
			return [];
		}

		$patterns = apply_filters( 'wpml_pb_anchor_shortcode_attribute_patterns', self::ANCHOR_ATTRIBUTE_PATTERNS );
		$anchors  = [];

		foreach ( $matches as list( , $tag, $attributes ) ) {
			if ( ! isset( $translatedAttributes[ $tag ] ) ) {
				continue;
			}

			foreach ( (array) shortcode_parse_atts( $attributes ) as $attribute => $value ) {
				if ( is_string( $attribute )
					&& is_string( $value )
					&& '' !== $value
					&& isset( $translatedAttributes[ $tag ][ $attribute ] )
					&& self::isAnchorName( $attribute, $patterns )
				) {
					$anchors[ $value ] = true;
				}
			}
		}

		return $anchors;
	}

	private static function isAttributeAnchor( array $attributeAnchors, string $value, string $translatedValue ): bool {
		return isset( $attributeAnchors[ $value ] ) && sanitize_html_class( $translatedValue ) === $translatedValue;
	}

	private static function translatedShortcodeAttributes(): array {
		$settings   = get_option( 'icl_st_settings', [] );
		$attributes = [];

		foreach ( (array) ( $settings['pb_shortcode'] ?? [] ) as $shortcode ) {
			$tag = $shortcode['tag']['value'] ?? null;

			if ( ! is_string( $tag ) || ! empty( $shortcode['tag']['raw-html'] ) ) {
				continue;
			}

			foreach ( (array) ( $shortcode['attributes'] ?? [] ) as $attribute ) {
				if ( isset( $attribute['value'] ) && is_string( $attribute['value'] ) ) {
					$attributes[ $tag ][ $attribute['value'] ] = true;
				}
			}
		}

		return $attributes;
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
