<?php

class WPML_Single_Url_Cache_Entry {

	const STATE_PENDING             = 'pending';
	const STATE_POSITIVE            = 'positive';
	const STATE_ROUTE               = 'route';
	const STATE_MISSING_TRANSLATION = 'missing_translation';
	const STATE_NEGATIVE            = 'negative';
	const STATE_FAILED              = 'failed';

	const PUBLIC_RESOLVED  = 'resolved';
	const PUBLIC_UNCHANGED = 'unchanged';
	const PUBLIC_DEFERRED  = 'deferred';

	public static function deferred_result( $url, $source_language ) {
		return [
			'url'                => $url,
			'source_post_id'     => null,
			'translated_post_id' => null,
			'source_language'    => $source_language,
			'resolution_state'   => self::PUBLIC_DEFERRED,
		];
	}

	public static function public_result( array $entry ) {
		$state = isset( $entry['state'] ) ? $entry['state'] : self::STATE_NEGATIVE;
		$url   = isset( $entry['resolved_url'] ) && null !== $entry['resolved_url']
			? $entry['resolved_url']
			: $entry['source_url'];

		$is_resolved = isset( $entry['source_url'] )
			? $url !== $entry['source_url']
			: in_array( $state, [ self::STATE_POSITIVE, self::STATE_ROUTE ], true );

		return [
			'url'                => $url,
			'source_post_id'     => ! empty( $entry['source_object_id'] ) && 'post' === $entry['source_object_kind']
				? (int) $entry['source_object_id']
				: null,
			'translated_post_id' => ! empty( $entry['translated_object_id'] ) && 'post' === $entry['source_object_kind']
				? (int) $entry['translated_object_id']
				: null,
			'source_language'    => isset( $entry['source_language'] ) ? $entry['source_language'] : null,
			'resolution_state'   => $is_resolved
				? self::PUBLIC_RESOLVED
				: self::PUBLIC_UNCHANGED,
		];
	}
}
