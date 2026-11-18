<?php

namespace WPML\TM\Editor;

class RichText {
	const TAG_PATTERN = '%<([a-zA-Z][a-zA-Z0-9:_-]*)((?:\s[^>]*)?)>%';

	const CLOSING_TAG_PATTERN = '%</([a-zA-Z][a-zA-Z0-9:_-]*)\s*>%';

	const QUOTED_SHORTCODE_PATTERN = '%\[[a-zA-Z][\w-]*[^\]]*[\'"][^\]]*\]%';

	const NEXT_ATTRIBUTE_PATTERN = '%\G\s*[^\s"\'=<>/]+(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|[^\s"\'=<>`]+))?%';


	public static function allowedHtml() {
		$allowed = wp_kses_allowed_html( 'post' );

		return (array) apply_filters( 'wpml_tm_editor_allowed_html', $allowed );
	}

	public static function sanitize( $html ) {
		return wp_kses( (string) $html, self::allowedHtml() );
	}

	public static function isRenderable( $html ) {
		$html = (string) $html;

		if ( false === strpos( $html, '<' ) ) {
			return true;
		}

		if ( preg_match( '%<(?:video|audio|picture)(?=[\s/>])%i', $html ) ) {
			return false;
		}

		if ( self::hasQuotedShortcodeInAttribute( $html ) ) {
			return false;
		}

		return self::sanitize( $html ) === wp_kses( $html, self::everythingUsedIn( $html ) );
	}

	private static function hasQuotedShortcodeInAttribute( $html ) {
		if ( false === strpos( $html, '[' ) || ! preg_match_all( self::TAG_PATTERN, $html, $matches, PREG_SET_ORDER ) ) {
			return false;
		}

		foreach ( $matches as $match ) {
			if ( self::hasValueCutInsideShortcode( $match[2] ) ) {
				return true;
			}
			foreach ( wp_kses_hair( $match[2], wp_allowed_protocols() ) as $attribute ) {
				$value = html_entity_decode( $attribute['value'], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
				if ( preg_match( self::QUOTED_SHORTCODE_PATTERN, $value ) ) {
					return true;
				}
			}
		}

		return false;
	}

	private static function hasValueCutInsideShortcode( $attributes ) {
		$length = strlen( $attributes );
		$offset = 0;
		$checkedTo = array( '"' => 0, "'" => 0 );
		while ( $offset < $length ) {
			if ( ! preg_match( self::NEXT_ATTRIBUTE_PATTERN, $attributes, $attribute, PREG_UNMATCHED_AS_NULL, $offset ) ) {
				$offset++;
				continue;
			}
			$offset += strlen( $attribute[0] );
			if ( isset( $attribute[1] ) ) {
				list( $value, $quote ) = array( $attribute[1], '"' );
			} elseif ( isset( $attribute[2] ) ) {
				list( $value, $quote ) = array( $attribute[2], "'" );
			} else {
				continue;
			}
			$open  = strrpos( $value, '[' );
			$close = strrpos( $value, ']' );
			if ( false === $open || ( false !== $close && $open < $close ) || $offset < $checkedTo[ $quote ] ) {
				continue;
			}
			$end = strpos( $attributes, ']' . $quote, $offset );
			if ( false === $end ) {
				$checkedTo[ $quote ] = $length;
				continue;
			}
			$end += 2;
			if ( ! self::readsAsAttributes( $attributes, $offset, $end ) ) {
				return true;
			}
			$checkedTo[ $quote ] = $end;
		}

		return false;
	}

	private static function readsAsAttributes( $attributes, $from, $to ) {
		while ( $from < $to ) {
			if ( ! preg_match( self::NEXT_ATTRIBUTE_PATTERN, $attributes, $attribute, 0, $from ) ) {
				return false;
			}
			$from += strlen( $attribute[0] );
		}

		return $from === $to;
	}

	private static function everythingUsedIn( $html ) {
		$used = array();

		if ( preg_match_all( self::CLOSING_TAG_PATTERN, $html, $closing ) ) {
			foreach ( $closing[1] as $element ) {
				$used[ strtolower( $element ) ] = array();
			}
		}

		if ( ! preg_match_all( self::TAG_PATTERN, $html, $matches, PREG_SET_ORDER ) ) {
			return $used;
		}

		foreach ( $matches as $match ) {
			$element = strtolower( $match[1] );
			if ( ! isset( $used[ $element ] ) ) {
				$used[ $element ] = array();
			}
			foreach ( wp_kses_hair( $match[2], wp_allowed_protocols() ) as $attribute ) {
				$used[ $element ][ strtolower( $attribute['name'] ) ] = true;
			}
		}

		return $used;
	}
}
