<?php

namespace WPML\TM\Taxonomy;

use WPML\Legacy\Component\WordsToTranslate\Domain\BareUrl;

class TranslatableTermMeta {

	const UNIT_ID_PREFIX = 'term-meta-';

	public static function keys( string $taxonomy, ?\WP_Term $term = null ): array {
		$keys = self::keysFromTmSettings();
		if ( $term ) {
			$keys = array_merge( $keys, self::resolvedKeysOfTerm( (int) $term->term_id ) );
		}

		$keys = apply_filters( 'wpml_translatable_term_meta', $keys, $taxonomy, $term );

		if ( ! is_array( $keys ) ) {
			return [];
		}

		return array_values( array_unique( array_filter( array_map( 'strval', $keys ) ) ) );
	}

	public static function values( \WP_Term $term ): array {
		return self::valuesById( (int) $term->term_id, self::keys( (string) $term->taxonomy, $term ), (string) $term->taxonomy );
	}

	public static function metaTextById( int $termId, array $keys, string $taxonomy = '' ): string {
		return implode( "\n", self::valuesOfTermById( $termId, $keys, $taxonomy ) );
	}

	public static function valuesOfTermById( int $termId, array $keys, string $taxonomy = '' ): array {
		$keys = array_unique( array_merge( $keys, self::resolvedKeysOfTerm( $termId ) ) );

		return self::valuesById( $termId, $keys, $taxonomy );
	}

	public static function countableText( array $values ): string {
		return implode(
			"\n",
			array_filter(
				$values,
				fn( $value ) => ! BareUrl::matches( (string) $value )
			)
		);
	}

	public static function metaText( \WP_Term $term ): string {
		return implode( "\n", self::values( $term ) );
	}

	private static function valuesById( int $termId, array $keys, string $taxonomy = '' ): array {
		if ( ! $termId ) {
			return [];
		}

		return self::valuesOfStored(
			$termId,
			$keys,
			function ( $key ) use ( $termId ) {
				return get_term_meta( $termId, $key, true );
			},
			$taxonomy
		);
	}

	private static function valuesOfStored( int $termId, array $keys, callable $storedValue, string $taxonomy ): array {
		$values = [];
		foreach ( $keys as $key ) {
			$value = $storedValue( $key );
			if ( is_array( $value ) ) {
				$values = array_merge( $values, self::translatableEntries( $key, $value ) );
				continue;
			}
			if ( ! is_string( $value ) && ! is_numeric( $value ) ) {
				continue;
			}
			$value = (string) $value;
			if ( '' === trim( $value ) ) {
				continue;
			}
			$values[ $key ] = $value;
		}

		$values = apply_filters( 'wpml_translatable_term_meta_values', $values, $termId, $taxonomy );

		return self::sanitizeValues( $values );
	}

	private static function sanitizeValues( $values ): array {
		if ( ! is_array( $values ) ) {
			return [];
		}

		$clean = [];
		foreach ( $values as $key => $value ) {
			if ( ! is_string( $key ) || '' === $key ) {
				continue;
			}
			if ( ! is_string( $value ) && ! is_numeric( $value ) ) {
				continue;
			}
			$value = (string) $value;
			if ( '' === trim( $value ) ) {
				continue;
			}
			$clean[ $key ] = $value;
		}

		return $clean;
	}

	public static function isTranslatableKey( string $metaKey, string $taxonomy ): bool {
		return in_array( $metaKey, self::keys( $taxonomy ), true )
			|| in_array( $metaKey, self::keysToTranslateAmong( [ $metaKey ] ), true );
	}

	public static function resolvedKeysOfTerm( int $termId ): array {
		$termMeta = $termId ? get_term_meta( $termId ) : [];

		return is_array( $termMeta ) ? self::keysToTranslateAmong( array_keys( $termMeta ) ) : [];
	}

	public static function entryKey( string $metaKey, array $path ): string {
		return $metaKey . implode( '', array_map( fn( $segment ) => '[' . rawurlencode( (string) $segment ) . ']', $path ) );
	}

	public static function parseEntryKey( string $entryKey ) {
		if ( ! preg_match( '/^(.+?)((?:\[[^\[\]]*\])+)$/', $entryKey, $matches ) ) {
			return null;
		}
		preg_match_all( '/\[([^\[\]]*)\]/', $matches[2], $segments );

		return [ $matches[1], array_map( 'rawurldecode', $segments[1] ) ];
	}

	private static function keysToTranslateAmong( array $metaKeys ): array {
		if ( ! $metaKeys ) {
			return [];
		}
		$preferences = wpml_resolve_custom_field_preferences( $metaKeys, 'term' );

		return array_map( 'strval', array_keys( $preferences, (int) WPML_TRANSLATE_CUSTOM_FIELD, true ) );
	}

	private static function translatableEntries( string $metaKey, array $value, array $path = [] ): array {
		$entries = [];
		foreach ( $value as $segment => $leaf ) {
			$leafPath = array_merge( $path, [ $segment ] );
			if ( is_array( $leaf ) ) {
				$entries = array_merge( $entries, self::translatableEntries( $metaKey, $leaf, $leafPath ) );
			} elseif ( self::isTranslatableLeaf( $metaKey, $leafPath, $leaf ) ) {
				$entries[ self::entryKey( $metaKey, $leafPath ) ] = (string) $leaf;
			}
		}

		return $entries;
	}

	private static function isTranslatableLeaf( string $metaKey, array $path, $leaf ): bool {
		if ( ! is_string( $leaf ) || '' === trim( $leaf ) || \WPML_String_Functions::is_not_translatable( $leaf ) ) {
			return false;
		}
		list( $postStyleFieldType ) = \WPML_TM_Field_Type_Encoding::encode( $metaKey, array_merge( [ 0 ], $path ) );

		return (bool) apply_filters( 'wpml_translation_job_post_meta_value_translated', 1, $postStyleFieldType );
	}

	private static function keysFromTmSettings(): array {
		global $iclTranslationManagement;

		if (
			! $iclTranslationManagement
			|| ! defined( 'WPML_TERM_META_SETTING_INDEX_PLURAL' )
			|| ! defined( 'WPML_TRANSLATE_CUSTOM_FIELD' )
			|| ! isset( $iclTranslationManagement->settings[ WPML_TERM_META_SETTING_INDEX_PLURAL ] )
			|| ! is_array( $iclTranslationManagement->settings[ WPML_TERM_META_SETTING_INDEX_PLURAL ] )
		) {
			return [];
		}

		$keys = [];
		foreach ( $iclTranslationManagement->settings[ WPML_TERM_META_SETTING_INDEX_PLURAL ] as $key => $state ) {
			if ( (int) WPML_TRANSLATE_CUSTOM_FIELD === (int) $state ) {
				$keys[] = (string) $key;
			}
		}

		return $keys;
	}

	const BATCH_CHUNK = 250;

	public static function valuesOfTermsByIds( array $termIds, array $keys, string $taxonomy = '' ): array {
		global $wpdb;

		$keys   = array_values( array_unique( array_map( 'strval', $keys ) ) );
		$values = [];

		foreach ( array_chunk( array_values( array_unique( array_map( 'intval', $termIds ) ) ), self::BATCH_CHUNK ) as $chunk ) {
			$ids      = array_values( array_filter( $chunk ) );
			$stored   = [];
			$resolved = [];

			if ( $ids ) {
				$chunkKeys    = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT DISTINCT meta_key FROM {$wpdb->prefix}termmeta WHERE term_id IN ( " . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ' )',
						$ids
					)
				);
				$resolvedKeys = self::keysToTranslateAmong( array_map( 'strval', is_array( $chunkKeys ) ? $chunkKeys : [] ) );
				$readKeys     = array_values( array_unique( array_merge( $keys, $resolvedKeys ) ) );

				if ( $readKeys ) {
					$isResolved = array_flip( $resolvedKeys );

					$rows = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT term_id, meta_key, meta_value FROM {$wpdb->prefix}termmeta WHERE term_id IN ( " . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ' ) AND meta_key IN ( ' . implode( ',', array_fill( 0, count( $readKeys ), '%s' ) ) . ' ) ORDER BY meta_id ASC',
							array_merge( $ids, $readKeys )
						),
						ARRAY_A
					);

					foreach ( $rows ?: [] as $row ) {
						$termId = (int) $row['term_id'];
						$key    = (string) $row['meta_key'];
						if ( isset( $stored[ $termId ] ) && array_key_exists( $key, $stored[ $termId ] ) ) {
							continue;
						}
						$stored[ $termId ][ $key ] = maybe_unserialize( $row['meta_value'] );
						if ( isset( $isResolved[ $key ] ) ) {
							$resolved[ $termId ][] = $key;
						}
					}
					$rows = null;
					$wpdb->flush();
				}
			}

			foreach ( $chunk as $termId ) {
				if ( ! $termId ) {
					$values[ $termId ] = [];
					continue;
				}

				$termStored        = $stored[ $termId ] ?? [];
				$values[ $termId ] = self::valuesOfStored(
					$termId,
					array_values( array_unique( array_merge( $keys, $resolved[ $termId ] ?? [] ) ) ),
					function ( $key ) use ( $termStored ) {
						return array_key_exists( $key, $termStored ) ? $termStored[ $key ] : '';
					},
					$taxonomy
				);
			}
		}

		return $values;
	}
}
