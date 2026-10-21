<?php

class WPML_Single_Url_Cache_Outcome {

	public static function build( array $entry, array $result ) {
		$state = isset( $result['_cache_state'] )
			? $result['_cache_state']
			: WPML_Single_Url_Cache_Entry::STATE_NEGATIVE;
		$now   = time();

		switch ( $state ) {
			case WPML_Single_Url_Cache_Entry::STATE_POSITIVE:
				$refresh_at = $now + 7 * DAY_IN_SECONDS;
				$expires_at = $now + 30 * DAY_IN_SECONDS;
				break;

			case WPML_Single_Url_Cache_Entry::STATE_ROUTE:
				$refresh_at = $now + 6 * HOUR_IN_SECONDS;
				$expires_at = $now + DAY_IN_SECONDS;
				break;

			case WPML_Single_Url_Cache_Entry::STATE_MISSING_TRANSLATION:
			case WPML_Single_Url_Cache_Entry::STATE_NEGATIVE:
			default:
				$negative_ttl = self::negative_ttl( $entry['cache_key'] );
				$refresh_at   = $now + $negative_ttl;
				$expires_at   = $refresh_at;
				break;
		}

		return [
			'state'                => $state,
			'resolved_url'         => isset( $result['url'] ) && is_string( $result['url'] )
				? $result['url']
				: $entry['source_url'],
			'source_object_kind'   => isset( $result['_source_object_kind'] )
				? $result['_source_object_kind']
				: null,
			'source_object_id'     => isset( $result['_source_object_id'] )
				? (int) $result['_source_object_id']
				: null,
			'translated_object_id' => isset( $result['translated_post_id'] )
				&& $result['translated_post_id']
					? (int) $result['translated_post_id']
					: (
						isset( $result['_translated_object_id'] ) && $result['_translated_object_id']
							? (int) $result['_translated_object_id']
							: null
					),
			'translation_trid'     => isset( $result['_translation_trid'] )
				? (int) $result['_translation_trid']
				: null,
			'refresh_after'        => gmdate( 'Y-m-d H:i:s', $refresh_at ),
			'expires_at'           => gmdate( 'Y-m-d H:i:s', $expires_at ),
		];
	}

	public static function negative_ttl( $cache_key ) {
		$range  = 12 * MINUTE_IN_SECONDS;
		$offset = hexdec( substr( $cache_key, 0, 4 ) ) % ( $range + 1 );

		return 24 * MINUTE_IN_SECONDS + $offset;
	}
}
