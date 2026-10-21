<?php

namespace WPML\Comments\Counts;

class CountServer {

	const SERVE_PRIORITY = 9;

	const GUARD_PRIORITY = PHP_INT_MAX;

	const DAILY_LIMIT = 86400;

	private $store;

	private $fallback;

	private $scheduler;

	private $sitepress;

	private $frames = array();

	private static $checked = array();

	public function __construct( Store $store, ?GlobalFallback $fallback = null, ?Scheduler $scheduler = null, ?\SitePress $sitepress = null ) {
		$this->store     = $store;
		$this->fallback  = $fallback;
		$this->scheduler = $scheduler;
		$this->sitepress = $sitepress;
	}

	public static function resetRequestCache() {
		self::$checked = array();
	}

	public function serve( $count, $post_id = 0 ) {
		$served         = $this->answer( $count, $post_id );
		$this->frames[] = $served;

		return null === $served ? $count : clone $served;
	}

	public function guard( $count ) {
		$served = array_pop( $this->frames );

		return null === $served ? $count : clone $served;
	}

	private function answer( $count, $post_id ) {
		if ( 0 !== (int) $post_id || ! empty( $count ) ) {
			return null;
		}

		$language = $this->currentLanguage();
		if ( ! $language || 'all' === $language ) {
			return null;
		}

		if ( ! $this->runsOnCurrentSite() ) {
			return null;
		}

		$store = $this->store->get();

		if ( null === $store ) {
			$buckets = $this->fallback()->buckets();
		} else {
			$buckets = self::bucketsOf( $store, $language );
		}

		$this->checkOncePerRequest( $store );

		return self::toObject( $buckets );
	}

	private function checkOncePerRequest( $store ) {
		$blog_id = get_current_blog_id();
		if ( isset( self::$checked[ $blog_id ] ) ) {
			return;
		}
		self::$checked[ $blog_id ] = true;

		if ( null === $store || $store['out_of_date'] > 0 ) {
			$this->scheduler()->ensureScheduled( $store );
		} elseif ( time() - $store['built_at'] > self::DAILY_LIMIT ) {
			$this->store->markOutOfDate();
		}
	}

	private static function bucketsOf( array $store, $language ) {
		$buckets = Exclusions::emptyBuckets();
		$stored  = isset( $store['counts'][ $language ] ) && is_array( $store['counts'][ $language ] )
			? $store['counts'][ $language ]
			: array();

		foreach ( $buckets as $bucket => $zero ) {
			if ( isset( $stored[ $bucket ] ) && is_numeric( $stored[ $bucket ] ) ) {
				$buckets[ $bucket ] = max( 0, (int) $stored[ $bucket ] );
			}
		}

		return $buckets;
	}

	private static function toObject( array $buckets ) {
		$count                   = new \stdClass();
		$count->approved         = $buckets['approved'];
		$count->spam             = $buckets['spam'];
		$count->trash            = $buckets['trash'];
		$count->{'post-trashed'} = $buckets['post-trashed'];
		$count->all              = $buckets['approved'] + $buckets['moderated'];
		$count->total_comments   = $count->all + $buckets['spam'];
		$count->moderated        = $buckets['moderated'];

		return $count;
	}

	private function currentLanguage() {
		$sitepress = $this->sitepress();

		return $sitepress ? $sitepress->get_current_language() : null;
	}

	private function runsOnCurrentSite() {
		if ( ! is_multisite() || ! ms_is_switched() ) {
			return true;
		}

		$sitepress = $this->sitepress();

		return $sitepress && $sitepress->is_setup_complete();
	}

	private function sitepress() {
		if ( ! $this->sitepress ) {
			global $sitepress;

			$this->sitepress = $sitepress instanceof \SitePress ? $sitepress : null;
		}

		return $this->sitepress;
	}

	private function fallback() {
		if ( ! $this->fallback ) {
			$this->fallback = new GlobalFallback();
		}

		return $this->fallback;
	}

	private function scheduler() {
		if ( ! $this->scheduler ) {
			$this->scheduler = new Scheduler();
		}

		return $this->scheduler;
	}
}
