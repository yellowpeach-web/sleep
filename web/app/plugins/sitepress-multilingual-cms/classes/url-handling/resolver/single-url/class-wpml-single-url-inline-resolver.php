<?php

class WPML_Single_Url_Inline_Resolver {

	const DEFAULT_MAX_ITEMS    = 10;
	const DEFAULT_MAX_MS       = 650;
	const DEFAULT_SLOW_ITEM_MS = 200;

	const BREAKER_TRANSIENT = 'wpml_single_url_inline_resolution_paused';
	const BREAKER_TTL       = 10 * MINUTE_IN_SECONDS;

	private $repository;

	private $cache;

	private $resolver;

	private $items_used = 0;

	private $ms_used = 0.0;

	public function __construct(
		WPML_Single_Url_Cache_Repository $repository,
		WPML_Single_Url_Resolution_Cache $cache
	) {
		$this->repository = $repository;
		$this->cache      = $cache;
	}

	public function set_resolver( WPML_Resolve_Single_Url $resolver ) {
		$this->resolver = $resolver;
	}

	public function try_resolve( $url, $source_language, $target_language ) {
		if ( ! $this->resolver ) {
			return null;
		}

		$budget = $this->budget();
		if (
			$this->items_used >= $budget['max_items']
			|| $this->ms_used >= $budget['max_ms']
			|| false !== get_transient( self::BREAKER_TRANSIENT )
		) {
			return null;
		}

		$identity = $this->cache->identity( $url, $source_language, $target_language );
		$entry    = $this->repository->claim_first_attempt(
			$identity['cache_key'],
			(int) $identity['generation']
		);
		if ( ! $entry ) {
			return null;
		}

		$started    = microtime( true );
		$elapsed_ms = 0.0;

		try {
			$result = $this->resolver->resolve_uncached_for_cache(
				$url,
				$target_language,
				$source_language
			);
		} catch ( Throwable $error ) {
			$this->trip_breaker();
			$this->repository->fail(
				$entry['cache_key'],
				$entry['lease_token'],
				isset( $entry['attempt_count'] ) ? (int) $entry['attempt_count'] : 1,
				get_class( $error ),
				isset( $entry['state'] ) ? $entry['state'] : ''
			);
			return null;
		} finally {
			$elapsed_ms        = ( microtime( true ) - $started ) * 1000;
			$this->items_used += 1;
			$this->ms_used    += $elapsed_ms;
		}

		if ( $elapsed_ms > $budget['slow_item_ms'] ) {
			$this->trip_breaker();
		}

		$outcome = WPML_Single_Url_Cache_Outcome::build( $entry, $result );
		if ( ! $this->repository->complete( $entry['cache_key'], $entry['lease_token'], $outcome ) ) {
			return null;
		}

		$stored = $this->repository->get( $entry['cache_key'] );
		if ( ! $stored ) {
			return null;
		}

		$this->cache->promote( $stored );

		return WPML_Single_Url_Cache_Entry::public_result( $stored );
	}

	private function budget() {
		$defaults = [
			'max_items'    => self::DEFAULT_MAX_ITEMS,
			'max_ms'       => self::DEFAULT_MAX_MS,
			'slow_item_ms' => self::DEFAULT_SLOW_ITEM_MS,
		];

		$budget = apply_filters( 'wpml_single_url_inline_resolution_budget', $defaults );
		if ( ! is_array( $budget ) ) {
			return $defaults;
		}

		foreach ( $defaults as $key => $default ) {
			$budget[ $key ] = isset( $budget[ $key ] ) && is_numeric( $budget[ $key ] )
				? max( 0, (int) $budget[ $key ] )
				: $default;
		}

		return $budget;
	}

	private function trip_breaker() {
		set_transient( self::BREAKER_TRANSIENT, time(), self::BREAKER_TTL );
	}
}
