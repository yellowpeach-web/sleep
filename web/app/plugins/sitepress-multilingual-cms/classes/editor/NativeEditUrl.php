<?php

namespace WPML\TM\Editor;

class NativeEditUrl {

	const FILTER = 'wpml_native_edit_url';

	public static function answer( $url, $postId, $screen, array $context ) {
		$answer = apply_filters( self::FILTER, $url, (int) $postId, (string) $screen, $context );

		return is_string( $answer ) && '' !== $answer ? $answer : $url;
	}

	public static function isAnswered( $url, $postId, $screen, array $context ) {
		return self::answer( $url, $postId, $screen, $context ) !== $url;
	}
}
