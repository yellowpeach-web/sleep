<?php

namespace WPML\TM\XLIFF;

class SegmentedSuggestion {

	const SEGMENT_ID_SUFFIX = ':wpml-seg-';

	const MAX_SEGMENTS = 50;

	const BLOCK_TAGS = array(
		'p',
		'div',
		'ul',
		'ol',
		'li',
		'dl',
		'dt',
		'dd',
		'h1',
		'h2',
		'h3',
		'h4',
		'h5',
		'h6',
		'blockquote',
		'br',
		'hr',
		'figure',
		'figcaption',
		'section',
		'article',
		'header',
		'footer',
		'aside',
		'main',
		'nav',
		'fieldset',
	);

	public static function split_pair( $source, $target ) {
		$source_pieces = self::tokenize( (string) $source );
		if ( null === $source_pieces ) {
			return null;
		}

		$source_texts = self::text_pieces( $source_pieces );
		$count        = count( $source_texts );
		if ( $count < 2 || $count > self::MAX_SEGMENTS ) {
			return null;
		}

		$target_pieces = self::tokenize( (string) $target );
		if ( null === $target_pieces ) {
			return null;
		}

		$target_texts = self::text_pieces( $target_pieces );
		if ( count( $target_texts ) !== $count ) {
			return null;
		}

		$pairs = array();
		foreach ( $source_texts as $index => $source_text ) {
			$pairs[] = array(
				'source' => $source_text,
				'target' => $target_texts[ $index ],
			);
		}

		return $pairs;
	}

	public static function reassemble_into( array $texts, $preferred_skeleton, $fallback_skeleton ) {
		if ( '' !== trim( (string) $preferred_skeleton ) ) {
			$reassembled = self::reassemble( (string) $preferred_skeleton, $texts );
			if ( null !== $reassembled ) {
				return $reassembled;
			}
		}

		return self::reassemble( (string) $fallback_skeleton, $texts );
	}

	public static function split_unit_id( $unit_id ) {
		if ( preg_match( '/^(.+)' . preg_quote( self::SEGMENT_ID_SUFFIX, '/' ) . '(\d+)$/', (string) $unit_id, $matches ) ) {
			return array( $matches[1], (int) $matches[2] );
		}

		return null;
	}

	private static function tokenize( $html ) {
		if ( false !== strpos( $html, '<!--' ) ) {
			return null;
		}

		$tokens = preg_split(
			'/(<\/?[a-zA-Z][a-zA-Z0-9]*(?:"[^"]*"|\'[^\']*\'|[^>"\'])*\/?>)/',
			$html,
			-1,
			PREG_SPLIT_DELIM_CAPTURE
		);
		if ( false === $tokens ) {
			return null;
		}

		$pieces = array();
		foreach ( $tokens as $token ) {
			if ( '' === $token ) {
				continue;
			}

			if ( '<' === $token[0] && preg_match( '/^<\/?([a-zA-Z][a-zA-Z0-9]*)/', $token, $tag ) ) {
				if ( ! in_array( strtolower( $tag[1] ), self::BLOCK_TAGS, true ) ) {
					return null;
				}
				$pieces[] = array(
					'kind' => 'markup',
					'html' => $token,
				);
			} else {
				$pieces[] = array(
					'kind' => 'text',
					'html' => $token,
				);
			}
		}

		return $pieces;
	}

	private static function text_pieces( array $pieces ) {
		$texts = array();
		foreach ( $pieces as $piece ) {
			if ( 'text' !== $piece['kind'] ) {
				continue;
			}

			$text = trim( $piece['html'] );
			if ( '' !== $text ) {
				$texts[] = $text;
			}
		}

		return $texts;
	}

	private static function reassemble( $skeleton, array $texts ) {
		$pieces = self::tokenize( $skeleton );
		if ( null === $pieces ) {
			return null;
		}

		$out        = '';
		$text_index = 0;
		foreach ( $pieces as $piece ) {
			$trimmed = trim( $piece['html'] );
			if ( 'text' !== $piece['kind'] || '' === $trimmed ) {
				$out .= $piece['html'];
				continue;
			}

			if ( ! array_key_exists( $text_index, $texts ) ) {
				return null;
			}

			$leading = substr( $piece['html'], 0, strlen( $piece['html'] ) - strlen( ltrim( $piece['html'] ) ) );
			$out    .= $leading . $texts[ $text_index ] . substr( $piece['html'], strlen( $leading ) + strlen( $trimmed ) );
			$text_index++;
		}

		if ( $text_index !== count( $texts ) ) {
			return null;
		}

		return $out;
	}
}
