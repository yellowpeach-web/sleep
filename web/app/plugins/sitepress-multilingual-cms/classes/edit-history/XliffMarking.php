<?php

namespace WPML\EditHistory;

class XliffMarking {

	const EMPTY_TARGET = 'empty';

	const CLIENT_EDIT = 'client-edit';

	private $newest;

	private $keys;

	private $edited;

	private function __construct( array $newest, array $keys, array $edited ) {
		$this->newest = $newest;
		$this->keys   = $keys;
		$this->edited = $edited;
	}

	public static function of( array $base, array $newest, array $original, Normaliser $normaliser ): self {
		$before = self::byKey( $normaliser->translation( $base ) );
		$now    = self::byKey( $normaliser->translation( $newest ) );

		$edited = [];
		foreach ( $now as $key => $text ) {
			if ( ( $before[ $key ] ?? null ) !== $text ) {
				$edited[ $key ] = true;
			}
		}

		return new self(
			self::byKey( $newest ),
			array_fill_keys( array_keys( $before + $now + self::byKey( $original ) ), true ),
			$edited
		);
	}

	public function rule( string $unitId, string $source, string $target ) {
		if ( 0 === strpos( $unitId, 'package-string-' ) ) {
			return self::EMPTY_TARGET;
		}
		if ( ! isset( $this->keys[ $unitId ] ) ) {
			return null;
		}
		if ( '' === trim( $this->newest[ $unitId ] ?? '' ) || '' === trim( $target ) || $target === $source ) {
			return self::EMPTY_TARGET;
		}

		return isset( $this->edited[ $unitId ] ) ? self::CLIENT_EDIT : null;
	}

	private static function byKey( array $units ): array {
		$texts = [];
		foreach ( $units as $unit ) {
			if ( isset( $unit['k'] ) ) {
				$texts[ (string) $unit['k'] ] = (string) ( $unit['x'] ?? '' );
			}
		}

		return $texts;
	}
}
