<?php

namespace WPML\TM\API\ATE;

use WPML\FP\Obj;
use WPML\LIB\WP\WordPress;
use function WPML\Container\make;

class NormalizedSuggestions {

	public static function get() {
		$response = WordPress::handleError(
			make( \WPML_TM_AMS_API::class )->getNormalizedSuggestionsCount()->getOrElse( array() )
		)->getOrElse( array() );

		$queuedSince = Obj::propOr( null, 'queued_since', $response );

		return array(
			'pending'     => (int) Obj::propOr( 0, 'pending', $response ),
			'queued'      => (int) Obj::propOr( 0, 'queued', $response ),
			'queuedSince' => is_string( $queuedSince ) && '' !== $queuedSince ? $queuedSince : null,
		);
	}

	public static function getCount() {
		return self::get()['pending'];
	}
}
