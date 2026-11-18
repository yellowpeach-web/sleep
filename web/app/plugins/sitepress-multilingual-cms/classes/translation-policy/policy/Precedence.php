<?php

namespace WPML\TranslationPolicy\Policy;

class Precedence {

	public static function resolve( array $rows, string $postType, string $fallbackEditor ): PolicyAnswer {
		$rows = self::sorted( array_values( array_filter( $rows, [ self::class, 'isKnownRow' ] ) ) );

		$editorRow  = null;
		$wpmlEditor = null;
		$methodRow  = null;
		foreach ( $rows as $row ) {
			if ( ! $editorRow && self::offersEditor( $row['editor'] ?? null, $postType ) ) {
				$editorRow = $row;
			}
			if ( null === $wpmlEditor && in_array( $row['editor'] ?? null, PolicyTable::WPML_EDITORS, true ) ) {
				$wpmlEditor = $row['editor'];
			}
			if ( ! $methodRow && in_array( $row['method'] ?? null, PolicyTable::METHODS, true ) ) {
				$methodRow = $row;
			}
		}

		$method         = $methodRow ? $methodRow['method'] : PolicyTable::FALLBACK_METHOD;
		$methodStrength = $methodRow ? $methodRow['strength'] : '';

		if ( ! $editorRow ) {
			return new PolicyAnswer( $fallbackEditor, $method, '', '', '', $methodStrength );
		}

		$editor = PolicyTable::EDITOR_WPML === $editorRow['editor']
			? ( $wpmlEditor ?? $fallbackEditor )
			: $editorRow['editor'];

		return new PolicyAnswer(
			$editor,
			$method,
			$editorRow['scope'],
			$editorRow['strength'],
			(string) ( $editorRow['source'] ?? '' ),
			$methodStrength,
			$editorRow['editor']
		);
	}

	public static function decidingRow( array $rows, string $column, string $postType ) {
		foreach ( self::sorted( array_values( array_filter( $rows, [ self::class, 'isKnownRow' ] ) ) ) as $row ) {
			if ( self::offersValue( $column, $row[ $column ] ?? null, $postType ) ) {
				return $row;
			}
		}

		return null;
	}

	public static function offersValue( string $column, $value, string $postType ): bool {
		return PolicyTable::COLUMN_EDITOR === $column
			? self::offersEditor( $value, $postType )
			: in_array( $value, PolicyTable::METHODS, true );
	}

	public static function offersEditor( $editor, string $postType ): bool {
		if ( ! in_array( $editor, PolicyTable::EDITORS, true ) ) {
			return false;
		}

		return ! ( PolicyTable::EDITOR_NATIVE === $editor && in_array( $postType, PolicyTable::TYPES_WITHOUT_NATIVE_EDITOR, true ) );
	}

	private static function isKnownRow( $row ): bool {
		return is_array( $row )
			&& in_array( $row['scope'] ?? null, PolicyTable::SCOPES, true )
			&& in_array( $row['strength'] ?? null, PolicyTable::STRENGTHS, true );
	}

	private static function sorted( array $rows ): array {
		$keys = [];
		foreach ( $rows as $position => $row ) {
			$keys[ $position ] = [ self::rank( $row ), $position ];
		}
		asort( $keys );

		$sorted = [];
		foreach ( array_keys( $keys ) as $position ) {
			$sorted[] = $rows[ $position ];
		}

		return $sorted;
	}

	private static function rank( array $row ): int {
		return (int) array_search( $row['strength'], PolicyTable::STRENGTHS, true ) * count( PolicyTable::SCOPES )
			+ (int) array_search( $row['scope'], PolicyTable::SCOPES, true );
	}
}
