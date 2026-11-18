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

	const LINE_BREAK_TAG = 'br';

	const INLINE_TAGS = array(
		'a',
		'strong',
		'b',
		'em',
		'i',
		'u',
		's',
		'span',
		'sup',
		'sub',
		'small',
		'mark',
		'code',
		'abbr',
		'img',
	);

	const VOID_INLINE_TAGS = array( 'br', 'img' );

	const TAG_EXPRESSION = '<\/?[a-zA-Z][a-zA-Z0-9]*(?:"[^"]*"|\'[^\']*\'|[^>"\'])*\/?>';

	const TAG_PATTERN = '/' . self::TAG_EXPRESSION . '/';

	const RUN_EDGES_PATTERN = '/^((?:\s|<br\b[^>]*>)*)(.*?)((?:\s|<br\b[^>]*>)*)$/is';

	public static function split_pair( $source, $target ) {
		$source_segments = self::segments( (string) $source );
		if ( null === $source_segments ) {
			return null;
		}

		$target_segments = self::segments( (string) $target );
		if ( null === $target_segments || count( $target_segments['texts'] ) !== count( $source_segments['texts'] ) ) {
			return null;
		}

		$pairs = array();
		foreach ( $source_segments['texts'] as $index => $source_text ) {
			$pairs[] = array(
				'source' => $source_text,
				'target' => $target_segments['texts'][ $index ],
			);
		}

		return $pairs;
	}

	public static function is_splittable( $html ) {
		return null !== self::segments( (string) $html );
	}

	public static function split_outdated( $old_source, $source, $translation ) {
		$old_segments = self::segments( (string) $old_source );
		$segments     = self::segments( (string) $source );
		if ( null === $old_segments || null === $segments || $old_segments['skeleton'] !== $segments['skeleton'] ) {
			return null;
		}

		$translated_segments = self::segments( (string) $translation );
		if ( null === $translated_segments || count( $translated_segments['texts'] ) !== count( $old_segments['texts'] ) ) {
			return null;
		}

		$split     = array();
		$unchanged = 0;
		foreach ( $segments['texts'] as $index => $text ) {
			$changed = $text !== $old_segments['texts'][ $index ];
			$split[] = array(
				'source'  => $text,
				'target'  => $translated_segments['texts'][ $index ],
				'changed' => $changed,
			);
			if ( ! $changed ) {
				$unchanged++;
			}
		}

		return $unchanged > 0 ? $split : null;
	}

	private static function segments( $html ) {
		$pieces = self::tokenize( $html );
		if ( null === $pieces ) {
			return null;
		}

		$texts    = array();
		$skeleton = array( '' );
		foreach ( $pieces as $piece ) {
			$last = count( $skeleton ) - 1;
			if ( 'text' !== $piece['kind'] ) {
				$skeleton[ $last ] .= $piece['html'];
				continue;
			}

			list( $leading, $core, $trailing ) = self::run_edges( $piece['html'] );
			if ( ! self::is_segment_core( $core ) ) {
				$skeleton[ $last ] .= $piece['html'];
				continue;
			}

			$skeleton[ $last ] .= $leading;
			$texts[]            = $core;
			$skeleton[]         = $trailing;
		}

		$count = count( $texts );
		if ( $count < 2 || $count > self::MAX_SEGMENTS ) {
			return null;
		}

		return array(
			'texts'    => $texts,
			'skeleton' => $skeleton,
		);
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
			'/(' . self::TAG_EXPRESSION . ')/',
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

			$kind = 'text';
			if ( '<' === $token[0] && preg_match( '/^<\/?([a-zA-Z][a-zA-Z0-9]*)/', $token, $tag ) ) {
				$tag_name = strtolower( $tag[1] );
				if ( self::LINE_BREAK_TAG !== $tag_name && ! in_array( $tag_name, self::INLINE_TAGS, true ) ) {
					if ( ! in_array( $tag_name, self::BLOCK_TAGS, true ) ) {
						return null;
					}
					$kind = 'markup';
				}
			}

			$last = count( $pieces ) - 1;
			if ( 'text' === $kind && $last >= 0 && 'text' === $pieces[ $last ]['kind'] ) {
				$pieces[ $last ]['html'] .= $token;
				continue;
			}

			$pieces[] = array(
				'kind' => $kind,
				'html' => $token,
			);
		}

		foreach ( $pieces as $piece ) {
			if ( 'text' === $piece['kind'] && ! self::inline_tags_balance( $piece['html'] ) ) {
				return null;
			}
		}

		return $pieces;
	}

	private static function inline_tags_balance( $html ) {
		if ( false === strpos( $html, '<' ) ) {
			return true;
		}

		if ( ! preg_match_all( '/<(\/?)([a-zA-Z][a-zA-Z0-9]*)(?:"[^"]*"|\'[^\']*\'|[^>"\'])*?(\/?)>/', $html, $tags, PREG_SET_ORDER ) ) {
			return true;
		}

		$open = array();
		foreach ( $tags as $tag ) {
			$name = strtolower( $tag[2] );
			if ( in_array( $name, self::VOID_INLINE_TAGS, true ) || '/' === $tag[3] ) {
				continue;
			}

			if ( '/' !== $tag[1] ) {
				$open[] = $name;
				continue;
			}

			if ( array_pop( $open ) !== $name ) {
				return false;
			}
		}

		return array() === $open;
	}

	private static function is_segment_core( $core ) {
		if ( '' === $core ) {
			return false;
		}

		if ( false === strpos( $core, '<' ) ) {
			return true;
		}

		$text = (string) preg_replace( self::TAG_PATTERN, '', $core );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		return 0 !== preg_match( '/[\p{L}\p{N}]/u', $text );
	}

	private static function run_edges( $html ) {
		if ( ! preg_match( self::RUN_EDGES_PATTERN, $html, $matches ) ) {
			return array( '', $html, '' );
		}

		return array( $matches[1], $matches[2], $matches[3] );
	}

	private static function reassemble( $skeleton, array $texts ) {
		$pieces = self::tokenize( $skeleton );
		if ( null === $pieces ) {
			return null;
		}

		$out        = '';
		$text_index = 0;
		foreach ( $pieces as $piece ) {
			if ( 'text' !== $piece['kind'] ) {
				$out .= $piece['html'];
				continue;
			}

			list( $leading, $core, $trailing ) = self::run_edges( $piece['html'] );
			if ( ! self::is_segment_core( $core ) ) {
				$out .= $piece['html'];
				continue;
			}

			if ( ! array_key_exists( $text_index, $texts ) ) {
				return null;
			}

			$out .= $leading . $texts[ $text_index ] . $trailing;
			$text_index++;
		}

		if ( $text_index !== count( $texts ) ) {
			return null;
		}

		return $out;
	}
}
