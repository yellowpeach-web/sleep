<?php

namespace WPML\EditHistory;

class UnitList {

	public static function encode( array $previous, array $current ): array {
		$positions = [];
		foreach ( $previous as $position => $unit ) {
			$positions[ self::identity( $unit ) ][] = $position;
		}

		$encoded = [];
		$count   = count( $current );
		for ( $i = 0; $i < $count; ) {
			$from = self::longestRunStart( $previous, $positions, $current, $i );
			if ( null === $from ) {
				$encoded[] = $current[ $i ];
				$i++;
				continue;
			}

			$length = self::runLength( $previous, $from, $current, $i );
			$last   = end( $encoded );
			if ( is_array( $last ) && isset( $last['span'] ) && $last['span'][0] + $last['span'][1] === $from ) {
				$encoded[ key( $encoded ) ]['span'][1] += $length;
			} else {
				$encoded[] = [ 'span' => [ $from, $length ] ];
			}
			$i += $length;
		}

		return $encoded;
	}

	public static function decode( array $encoded, array $previous ) {
		$units = [];
		foreach ( $encoded as $item ) {
			if ( ! isset( $item['span'] ) ) {
				$units[] = $item;
				continue;
			}

			[ $from, $count ] = $item['span'];
			if ( $from < 0 || $count < 1 || $from + $count > count( $previous ) ) {
				return null;
			}
			foreach ( array_slice( $previous, $from, $count ) as $unit ) {
				$units[] = $unit;
			}
		}

		return $units;
	}

	public static function hash( array $units ): string {
		return md5( (string) wp_json_encode( array_map( [ self::class, 'identity' ], $units ) ) );
	}

	private static function identity( array $unit ): string {
		return ( $unit['k'] ?? '' ) . "\0" . ( $unit['x'] ?? '' );
	}

	private static function longestRunStart( array $previous, array $positions, array $current, int $at ) {
		$best       = null;
		$bestLength = 0;
		foreach ( $positions[ self::identity( $current[ $at ] ) ] ?? [] as $from ) {
			$length = self::runLength( $previous, $from, $current, $at );
			if ( $length > $bestLength ) {
				$best       = $from;
				$bestLength = $length;
			}
		}

		return $best;
	}

	private static function runLength( array $previous, int $from, array $current, int $at ): int {
		$length = 0;
		while (
			isset( $previous[ $from + $length ], $current[ $at + $length ] )
			&& self::identity( $previous[ $from + $length ] ) === self::identity( $current[ $at + $length ] )
		) {
			$length++;
		}

		return $length;
	}
}
