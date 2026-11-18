<?php

namespace WPML\PB\Gutenberg;

class RebuildOverlay {

	public static function merge( array $translation, array $original, array $rebuilt, callable $isNew, array $deleted ) {
		if ( array_column( $original, 'name' ) !== array_column( $rebuilt, 'name' ) ) {
			return null;
		}

		$ours   = self::byUid( $translation );
		$theirs = self::byUid( $original );
		if ( null === $ours || null === $theirs || array_diff_key( $ours, $theirs ) ) {
			return null;
		}

		$shared = array_values( array_intersect( array_keys( $theirs ), array_keys( $ours ) ) );
		$kept   = LongestCommonOrder::inEvery( $shared, array_values( array_intersect( array_keys( $ours ), $shared ) ) );
		if ( count( $kept ) !== count( $shared ) ) {
			return null;
		}

		$deleted = array_flip( array_map( 'strval', $deleted ) );
		$blocks  = [];
		foreach ( $original as $position => $block ) {
			$uid = $block['uid'];

			if ( isset( $ours[ $uid ] ) ) {
				if ( $ours[ $uid ]['name'] !== $block['name'] ) {
					return null;
				}
				$blocks[] = $ours[ $uid ]['block'];
				continue;
			}

			if ( isset( $deleted[ $uid ] ) ) {
				continue;
			}

			$new = $isNew( $uid );
			if ( null === $new ) {
				return null;
			}
			if ( $new ) {
				$blocks[] = $rebuilt[ $position ]['block'];
			}
		}

		return $blocks;
	}

	private static function byUid( array $blocks ) {
		$indexed = [];
		foreach ( $blocks as $block ) {
			$uid = (string) $block['uid'];
			if ( '' === $uid || isset( $indexed[ $uid ] ) ) {
				return null;
			}
			$indexed[ $uid ] = $block;
		}

		return $indexed;
	}
}
