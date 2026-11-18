<?php

namespace WPML\PB\Helper;

class TranslationKses {

	const DYNAMIC_CONTENT_TOKEN = '#\{\{dc:[a-z0-9_:.\-]+\}\}#i';

	public static function filter( string $text ): string {
		if ( false === strpos( $text, '<' ) ) {
			return $text;
		}

		$tokens            = [];
		$placeholderPrefix = 'wpml-dc-' . md5( $text ) . '-';
		$protectedText     = preg_replace_callback(
			self::DYNAMIC_CONTENT_TOKEN,
			function ( $match ) use ( &$tokens, $placeholderPrefix ) {
				$placeholder            = $placeholderPrefix . count( $tokens ) . '-';
				$tokens[ $placeholder ] = $match[0];

				return $placeholder;
			},
			$text
		);

		return strtr( wp_kses_post( $protectedText ), $tokens );
	}
}
