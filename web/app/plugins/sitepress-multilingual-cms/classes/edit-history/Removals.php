<?php

namespace WPML\EditHistory;

class Removals {

	private static $writes = [];

	public static function run( int $originalId, string $language, array $unitIds, callable $write ): array {
		self::$writes[] = [
			'original' => $originalId,
			'language' => $language,
			'units'    => array_fill_keys( $unitIds, true ),
			'dropped'  => [],
			'rebuilt'  => false,
		];

		try {
			$result = $write();
			$done   = end( self::$writes );

			return [ $result, $done['rebuilt'] ? $done['dropped'] : null ];
		} finally {
			array_pop( self::$writes );
		}
	}

	public static function unitsFor( int $originalId, string $language ) {
		$write = self::current( $originalId, $language );

		return null === $write ? null : self::$writes[ $write ]['units'];
	}

	public static function rebuilt( int $originalId, string $language, array $dropped ) {
		$write = self::current( $originalId, $language );
		if ( null === $write ) {
			return;
		}

		self::$writes[ $write ]['rebuilt'] = true;
		self::$writes[ $write ]['dropped'] = array_merge( self::$writes[ $write ]['dropped'], $dropped );
	}

	private static function current( int $originalId, string $language ) {
		for ( $i = count( self::$writes ) - 1; $i >= 0; $i-- ) {
			if ( self::$writes[ $i ]['original'] === $originalId && self::$writes[ $i ]['language'] === $language ) {
				return $i;
			}
		}

		return null;
	}
}
