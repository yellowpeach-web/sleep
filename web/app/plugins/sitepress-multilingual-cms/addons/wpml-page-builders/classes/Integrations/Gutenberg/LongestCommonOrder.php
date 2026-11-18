<?php

namespace WPML\PB\Gutenberg;

class LongestCommonOrder {

	public static function inEvery( array $original, array $translated ): array {
		$position = array_flip( array_map( 'strval', $translated ) );
		$at       = [];

		foreach ( $original as $uid ) {
			$at[] = $position[ (string) $uid ];
		}

		$count  = count( $at );
		$ending = array_fill( 0, $count, 1 );
		$from   = array_fill( 0, $count, 1 );

		for ( $k = 0; $k < $count; $k++ ) {
			for ( $i = 0; $i < $k; $i++ ) {
				if ( $at[ $i ] < $at[ $k ] ) {
					$ending[ $k ] = max( $ending[ $k ], $ending[ $i ] + 1 );
				}
			}
		}

		for ( $k = $count - 1; $k >= 0; $k-- ) {
			for ( $i = $count - 1; $i > $k; $i-- ) {
				if ( $at[ $i ] > $at[ $k ] ) {
					$from[ $k ] = max( $from[ $k ], $from[ $i ] + 1 );
				}
			}
		}

		$longest = $count ? max( $ending ) : 0;
		$on_run  = [];

		for ( $k = 0; $k < $count; $k++ ) {
			if ( $ending[ $k ] + $from[ $k ] - 1 === $longest ) {
				$on_run[ $ending[ $k ] ][] = $k;
			}
		}

		$kept = [];

		foreach ( $on_run as $step ) {
			if ( 1 === count( $step ) ) {
				$kept[ $step[0] ] = $original[ $step[0] ];
			}
		}

		ksort( $kept );

		return array_values( $kept );
	}
}
