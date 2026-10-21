<?php

namespace WPML\TM\Jobs;

class BlockDelimiterAttributes {

	const DELIMITER = '/<!--\s+(?P<closer>\/)?wp:(?P<namespace>[a-z][a-z0-9_-]*\/)?(?P<name>[a-z][a-z0-9_-]*)\s+(?P<attrs>{(?:(?:[^}]+|}+(?=})|(?!}\s+\/?-->).)*+)?}\s+)?(?P<void>\/)?-->/s';

	const QUOTE_ENTITY = '/&(?:quot|#0*34|#x0*22);/i';

	public static function restore( $translated, $original ) {
		if ( ! is_string( $translated ) || false === strpos( $translated, '<!--' ) || false === strpos( $translated, 'wp:' ) ) {
			return $translated;
		}

		$openers = self::openers( $translated );
		if ( ! self::has_broken_attributes( $openers ) ) {
			return $translated;
		}

		$original_openers = is_string( $original ) ? self::openers( $original ) : [];
		$trees_match      = self::names( $openers ) === self::names( $original_openers );

		$offset = 0;
		foreach ( $openers as $index => $opener ) {
			if ( null === $opener['attrs'] || self::parses( $opener['attrs'] ) ) {
				continue;
			}

			$attrs = preg_replace( self::QUOTE_ENTITY, '"', $opener['attrs'] );
			if ( ! self::parses( $attrs ) ) {
				$attrs = null;
				if ( $trees_match ) {
					$candidate = $original_openers[ $index ]['attrs'];
					$attrs     = null !== $candidate && self::parses( $candidate ) ? $candidate : null;
				}
			}

			if ( null === $attrs ) {
				continue;
			}

			$position   = $opener['attrs_offset'] + $offset;
			$translated = substr_replace( $translated, $attrs, $position, strlen( $opener['attrs'] ) );
			$offset    += strlen( $attrs ) - strlen( $opener['attrs'] );
		}

		return $translated;
	}

	private static function openers( $content ) {
		if ( ! preg_match_all( self::DELIMITER, $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
			return [];
		}

		$openers = [];
		foreach ( $matches as $match ) {
			if ( isset( $match['closer'] ) && '' !== $match['closer'][0] && -1 !== $match['closer'][1] ) {
				continue;
			}

			$has_attrs = isset( $match['attrs'] ) && -1 !== $match['attrs'][1] && '' !== $match['attrs'][0];
			$attrs     = $has_attrs ? rtrim( $match['attrs'][0] ) : null;
			$namespace = isset( $match['namespace'] ) && -1 !== $match['namespace'][1] ? $match['namespace'][0] : '';

			$openers[] = [
				'name'         => ( '' !== $namespace ? $namespace : 'core/' ) . $match['name'][0],
				'attrs'        => $attrs,
				'attrs_offset' => $has_attrs ? $match['attrs'][1] : -1,
			];
		}

		return $openers;
	}

	private static function has_broken_attributes( array $openers ) {
		foreach ( $openers as $opener ) {
			if ( null !== $opener['attrs'] && ! self::parses( $opener['attrs'] ) ) {
				return true;
			}
		}

		return false;
	}

	private static function names( array $openers ) {
		return array_map(
			function ( $opener ) {
				return $opener['name'];
			},
			$openers
		);
	}

	private static function parses( $attrs ) {
		return is_array( json_decode( $attrs, true ) );
	}
}
