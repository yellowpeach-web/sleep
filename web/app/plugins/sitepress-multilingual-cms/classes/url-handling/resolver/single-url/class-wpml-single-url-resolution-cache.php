<?php

class WPML_Single_Url_Resolution_Cache {

	const CACHE_GROUP = 'wpml_single_url_resolution';

	private $repository;

	private $generation;

	private $scheduler;

	private $request_cache = [];

	public function __construct(
		WPML_Single_Url_Cache_Repository $repository,
		WPML_Single_Url_Cache_Generation $generation,
		WPML_Single_Url_Cache_Scheduler $scheduler
	) {
		$this->repository = $repository;
		$this->generation = $generation;
		$this->scheduler  = $scheduler;
	}

	public function get_or_defer( $url, $source_language, $target_language ) {
		$identity  = $this->identity( $url, $source_language, $target_language );
		$cache_key = $identity['cache_key'];

		if ( isset( $this->request_cache[ $cache_key ] ) ) {
			return $this->request_cache[ $cache_key ];
		}

		$found = false;
		$entry = wp_cache_get( $this->object_cache_key( $cache_key ), self::CACHE_GROUP, false, $found );
		if ( ! $found || ! is_array( $entry ) ) {
			$entry = $this->repository->get( $cache_key );
			if ( $entry ) {
				$this->put_object_cache( $cache_key, $entry );
			}
		}

		if ( $entry ) {
			$result = $this->interpret( $identity, $entry );
			if ( $result ) {
				$this->request_cache[ $cache_key ] = $result;
				return $result;
			}
		}

		if ( $this->repository->ensure_pending( $identity ) ) {
			$this->scheduler->schedule( get_current_blog_id() );
		}

		$result                            = WPML_Single_Url_Cache_Entry::deferred_result( $url, $source_language );
		$this->request_cache[ $cache_key ] = $result;

		return $result;
	}

	public function promote( array $entry ) {
		if ( empty( $entry['cache_key'] ) ) {
			return;
		}

		$cache_key                         = strtolower( $entry['cache_key'] );
		$result                            = WPML_Single_Url_Cache_Entry::public_result( $entry );
		$this->request_cache[ $cache_key ] = $result;
		$this->put_object_cache( $cache_key, $entry );
	}

	public function delete_object_cache_keys( array $cache_keys ) {
		foreach ( $cache_keys as $cache_key ) {
			$cache_key = strtolower( $cache_key );
			unset( $this->request_cache[ $cache_key ] );
			wp_cache_delete( $this->object_cache_key( $cache_key ), self::CACHE_GROUP );
		}
	}

	public function identity( $url, $source_language, $target_language ) {
		$generation = $this->generation->get();
		$blog_id    = get_current_blog_id();

		return [
			'cache_key'       => WPML_Single_Url_Cache_Key::build(
				$blog_id,
				$generation,
				$url,
				$source_language,
				$target_language
			),
			'url_hash'        => WPML_Single_Url_Cache_Key::url_hash( $url ),
			'generation'      => $generation,
			'source_url'      => $url,
			'source_language' => $source_language,
			'target_language' => $target_language,
		];
	}

	private function interpret( array $identity, array $entry ) {
		$state = isset( $entry['state'] ) ? $entry['state'] : '';
		$now   = time();

		if ( WPML_Single_Url_Cache_Entry::STATE_PENDING === $state ) {
			$this->scheduler->schedule( get_current_blog_id() );
			return WPML_Single_Url_Cache_Entry::deferred_result(
				$identity['source_url'],
				$identity['source_language']
			);
		}

		if ( WPML_Single_Url_Cache_Entry::STATE_FAILED === $state ) {
			if ( empty( $entry['next_attempt_at'] ) || strtotime( $entry['next_attempt_at'] . ' UTC' ) <= $now ) {
				$this->repository->reset_to_pending( $identity['cache_key'] );
				wp_cache_delete( $this->object_cache_key( $identity['cache_key'] ), self::CACHE_GROUP );
				$this->scheduler->schedule( get_current_blog_id() );
			}

			return WPML_Single_Url_Cache_Entry::deferred_result(
				$identity['source_url'],
				$identity['source_language']
			);
		}

		$hard_expired = ! empty( $entry['expires_at'] )
			&& strtotime( $entry['expires_at'] . ' UTC' ) <= $now;

		if ( $hard_expired ) {
			$this->repository->reset_to_pending( $identity['cache_key'] );
			wp_cache_delete( $this->object_cache_key( $identity['cache_key'] ), self::CACHE_GROUP );
			$this->scheduler->schedule( get_current_blog_id() );

			return WPML_Single_Url_Cache_Entry::deferred_result(
				$identity['source_url'],
				$identity['source_language']
			);
		}

		$refresh_due = ! empty( $entry['refresh_after'] )
			&& strtotime( $entry['refresh_after'] . ' UTC' ) <= $now;

		if ( $refresh_due && empty( $entry['refresh_requested'] ) ) {
			$this->repository->request_refresh( $identity['cache_key'] );
			$entry['refresh_requested'] = 1;
			$this->put_object_cache( $identity['cache_key'], $entry );
			$this->scheduler->schedule( get_current_blog_id() );
		} elseif (
			$refresh_due
			&& ! empty( $entry['refresh_requested'] )
			&& ! empty( $entry['next_attempt_at'] )
			&& strtotime( $entry['next_attempt_at'] . ' UTC' ) <= $now
		) {
			$this->scheduler->schedule( get_current_blog_id() );
		}

		return WPML_Single_Url_Cache_Entry::public_result( $entry );
	}

	private function put_object_cache( $cache_key, array $entry ) {
		$ttl = DAY_IN_SECONDS;
		if ( ! empty( $entry['expires_at'] ) ) {
			$ttl = max( 1, strtotime( $entry['expires_at'] . ' UTC' ) - time() );
		}

		wp_cache_set(
			$this->object_cache_key( $cache_key ),
			$entry,
			self::CACHE_GROUP,
			$ttl
		);
	}

	private function object_cache_key( $cache_key ) {
		return get_current_blog_id() . ':' . strtolower( $cache_key );
	}
}
