<?php

namespace WPML\Blocks;

class AttributeUrls {

	const LINK_KEYS = 'url|href';

	public static function fromAttributes( $attributes ) {
		$urls = [];
		self::collectFromValue( $attributes, $urls );

		return array_values( array_unique( $urls ) );
	}

	public static function fromBody( $body ) {
		$urls = [];

		if ( ! is_string( $body ) || '' === $body ) {
			return $urls;
		}

		if ( ! function_exists( 'parse_blocks' ) || ! function_exists( 'has_blocks' ) || ! has_blocks( $body ) ) {
			return $urls;
		}

		self::collectFromBlocks( parse_blocks( $body ), $urls );

		return array_values( array_unique( $urls ) );
	}

	public static function replaceInDelimiters( $body, array $replacements ) {
		if ( ! is_string( $body ) || ! $replacements || false === strpos( $body, '<!-- wp:' ) ) {
			return $body;
		}

		$result = preg_replace_callback(
			'#<!-- wp:\S+ (\{.*?\})\s*/?-->#s',
			function ( $matches ) use ( $replacements ) {
				$delimiter = $matches[0];

				foreach ( $replacements as $from => $to ) {
					$delimiter = self::replaceEncodedValue( $delimiter, (string) $from, (string) $to );
				}

				return $delimiter;
			},
			$body
		);

		return null === $result ? $body : $result;
	}

	public static function replaceInBlock( $block, $from, $to ) {
		if ( ! is_string( $block ) || '' === (string) $from ) {
			return $block;
		}

		$block = self::replaceInDelimiters( $block, [ $from => $to ] );

		return str_replace( $from, $to, $block );
	}

	public static function stickyLinkRule( $domainPattern, $langPrefix, $dirPath, $target ) {
		$encoded = self::encodeValue( $target );

		if ( null === $encoded ) {
			return [];
		}

		$slash   = self::slashPattern();
		$address = '(?:https?:' . $slash . $slash . $domainPattern . ')?'
			. self::literalPattern( $langPrefix ) . $slash . self::literalPattern( $dirPath );

		return [
			'@"(' . self::LINK_KEYS . ')"(\s*:\s*)"' . $address . '"@i'
				=> '"${1}"${2}"' . self::escapeReplacement( $encoded ) . '"',
		];
	}

	public static function encodeValue( $value ) {
		$encoded = json_encode( (string) $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		if ( ! is_string( $encoded ) || strlen( $encoded ) < 2 ) {
			return null;
		}

		return strtr(
			substr( $encoded, 1, -1 ),
			[
				'\\\\' => '\\u005c',
				'--'   => '\\u002d\\u002d',
				'<'    => '\\u003c',
				'>'    => '\\u003e',
				'&'    => '\\u0026',
				'\\"'  => '\\u0022',
			]
		);
	}

	public static function decodeValue( $value ) {
		$decoded = json_decode( '"' . $value . '"' );

		return is_string( $decoded ) ? $decoded : (string) $value;
	}

	public static function slashPattern() {
		return '(?:\\\\/|/)';
	}

	public static function literalPattern( $literal, $delimiter = '@' ) {
		$literal = (string) $literal;

		if ( '' === $literal ) {
			return '';
		}

		$chars = preg_split( '//u', $literal, -1, PREG_SPLIT_NO_EMPTY );

		if ( ! is_array( $chars ) ) {
			$chars = str_split( $literal );
		}

		$pattern = '';

		foreach ( $chars as $char ) {
			$pattern .= self::charPattern( $char, $delimiter );
		}

		return $pattern;
	}

	public static function escapeReplacement( $replacement ) {
		return str_replace( [ '\\', '$' ], [ '\\\\', '\\$' ], (string) $replacement );
	}

	private static function replaceEncodedValue( $subject, $from, $to ) {
		if ( '' === $from ) {
			return $subject;
		}

		$result = preg_replace_callback(
			'@"' . self::literalPattern( $from ) . '"@i',
			function ( $matches ) use ( $to ) {
				$encoded = self::encodeLike( $matches[0], $to );

				return null === $encoded ? $matches[0] : '"' . $encoded . '"';
			},
			$subject
		);

		return null === $result ? $subject : $result;
	}

	private static function encodeLike( $matched, $to ) {
		$encoded = self::encodeValue( $to );

		if ( null === $encoded ) {
			return null;
		}

		if ( false !== strpos( $matched, '\\/' ) ) {
			$encoded = str_replace( '/', '\\/', $encoded );
		}

		return $encoded;
	}

	private static function charPattern( $char, $delimiter ) {
		$alternatives = [
			'/'  => self::slashPattern(),
			'&'  => '(?:\\\\u0026|&amp;|&)',
			'<'  => '(?:\\\\u003c|<)',
			'>'  => '(?:\\\\u003e|>)',
			'"'  => '(?:\\\\u0022|\\\\")',
			'-'  => '(?:\\\\u002d|-)',
			'\\' => '(?:\\\\u005c|\\\\\\\\)',
			"'"  => '(?:\\\\u0027|\')',
		];

		if ( isset( $alternatives[ $char ] ) ) {
			return $alternatives[ $char ];
		}

		if ( 1 === strlen( $char ) ) {
			return preg_quote( $char, $delimiter );
		}

		$escaped = json_encode( $char );
		$escaped = is_string( $escaped ) ? substr( $escaped, 1, -1 ) : '';

		if ( '' === $escaped || $escaped === $char ) {
			return preg_quote( $char, $delimiter );
		}

		return '(?:' . preg_quote( $char, $delimiter ) . '|' . preg_quote( $escaped, $delimiter ) . ')';
	}

	private static function collectFromBlocks( $blocks, array &$urls ) {
		if ( ! is_array( $blocks ) ) {
			return;
		}

		foreach ( $blocks as $block ) {
			if ( ! empty( $block['attrs'] ) ) {
				self::collectFromValue( $block['attrs'], $urls );
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				self::collectFromBlocks( $block['innerBlocks'], $urls );
			}
		}
	}

	private static function collectFromValue( $value, array &$urls ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $item ) {
				self::collectFromValue( $item, $urls );
			}

			return;
		}

		if ( is_string( $value ) && preg_match( '#^https?://#i', $value ) ) {
			$urls[] = $value;
		}
	}
}
