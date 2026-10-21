<?php

namespace WPML\TM\Editor;

class RichText {
	const TAG_PATTERN = '%<([a-zA-Z][a-zA-Z0-9:_-]*)((?:\s[^>]*)?)>%';

	const CLOSING_TAG_PATTERN = '%</([a-zA-Z][a-zA-Z0-9:_-]*)\s*>%';

	const QUOTED_SHORTCODE_PATTERN = '%\[[a-zA-Z][\w-]*[^\]]*[\'"][^\]]*\]%';


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
			foreach ( wp_kses_hair( $match[2], wp_allowed_protocols() ) as $attribute ) {
				$value = html_entity_decode( $attribute['value'], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
				if ( preg_match( self::QUOTED_SHORTCODE_PATTERN, $value ) ) {
					return true;
				}
			}
		}

		return false;
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
