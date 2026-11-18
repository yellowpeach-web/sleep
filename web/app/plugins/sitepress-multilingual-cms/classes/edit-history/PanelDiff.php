<?php

namespace WPML\EditHistory;

class PanelDiff {

	const MAX_CELLS = 250000;

	const LEADING_KEYS = [ 'title', 'URL' ];

	public static function rows( array $client, array $ate ): array {
		[ $clientKeyed, $clientBlocks ] = self::split( $client );
		[ $ateKeyed, $ateBlocks ]       = self::split( $ate );

		$keys    = array_keys( $clientKeyed + $ateKeyed );
		$leading = array_values( array_intersect( $keys, self::LEADING_KEYS ) );
		$rest    = array_values( array_diff( $keys, self::LEADING_KEYS ) );

		return array_merge(
			self::keyedRows( $leading, $clientKeyed, $ateKeyed ),
			self::blockRows( $clientBlocks, $ateBlocks ),
			self::keyedRows( $rest, $clientKeyed, $ateKeyed )
		);
	}

	private static function split( array $units ): array {
		$keyed  = [];
		$blocks = [];
		foreach ( $units as $unit ) {
			$text = self::plain( (string) ( $unit['x'] ?? '' ) );
			if ( isset( $unit['k'] ) ) {
				$keyed[ (string) $unit['k'] ] = $text;
			} else {
				$blocks[] = $text;
			}
		}

		return [ $keyed, $blocks ];
	}

	private static function keyedRows( array $keys, array $client, array $ate ): array {
		$rows = [];
		foreach ( $keys as $key ) {
			$ours   = $client[ $key ] ?? null;
			$theirs = $ate[ $key ] ?? null;
			if ( $ours !== $theirs ) {
				$rows[] = self::row( (string) $key, $ours, $theirs );
			}
		}

		return $rows;
	}

	private static function blockRows( array $client, array $ate ): array {
		$rows = [];
		foreach ( self::align( $client, $ate ) as [ $i, $j ] ) {
			$ours   = null === $i ? null : $client[ $i ];
			$theirs = null === $j ? null : $ate[ $j ];
			if ( $ours !== $theirs ) {
				$rows[] = self::row( '', $ours, $theirs );
			}
		}

		return $rows;
	}

	private static function align( array $a, array $b ): array {
		$n = count( $a );
		$m = count( $b );
		if ( $n * $m > self::MAX_CELLS ) {
			return self::inOrder( array_keys( $a ), array_keys( $b ) );
		}

		$longest = array_fill( 0, $n + 1, array_fill( 0, $m + 1, 0 ) );
		for ( $i = $n - 1; $i >= 0; $i-- ) {
			for ( $j = $m - 1; $j >= 0; $j-- ) {
				$longest[ $i ][ $j ] = $a[ $i ] === $b[ $j ] ? $longest[ $i + 1 ][ $j + 1 ] + 1 : max( $longest[ $i + 1 ][ $j ], $longest[ $i ][ $j + 1 ] );
			}
		}

		$pairs = [];
		$gapA  = [];
		$gapB  = [];
		for ( $i = 0, $j = 0; $i < $n || $j < $m; ) {
			if ( $i < $n && $j < $m && $a[ $i ] === $b[ $j ] ) {
				$pairs = array_merge( $pairs, self::inOrder( $gapA, $gapB ), [ [ $i++, $j++ ] ] );
				$gapA  = [];
				$gapB  = [];
			} elseif ( $j >= $m || ( $i < $n && $longest[ $i + 1 ][ $j ] >= $longest[ $i ][ $j + 1 ] ) ) {
				$gapA[] = $i++;
			} else {
				$gapB[] = $j++;
			}
		}

		return array_merge( $pairs, self::inOrder( $gapA, $gapB ) );
	}

	private static function inOrder( array $a, array $b ): array {
		$pairs = [];
		$count = max( count( $a ), count( $b ) );
		for ( $k = 0; $k < $count; $k++ ) {
			$pairs[] = [ $a[ $k ] ?? null, $b[ $k ] ?? null ];
		}

		return $pairs;
	}

	private static function row( string $key, $client, $ate ): array {
		return [
			'key'    => $key,
			'client' => $client,
			'ate'    => $ate,
		];
	}

	private static function plain( string $text ): string {
		$text = html_entity_decode( strip_tags( $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	}
}
