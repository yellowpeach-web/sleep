<?php

namespace WPML\Comments\Counts;

class ActivityList {

	const NOTE_PRIORITY = PHP_INT_MIN;

	const RECOGNISE_PRIORITY = 1000;

	const RESTRICT_PRIORITY = PHP_INT_MAX;

	const CACHE_DOMAIN_PREFIX = 'wpml_activity_';

	const CHECK_DOMAIN_PREFIX = 'wpml_activity_check_';

	const FREE_VARS = array( 'number', 'offset', 'status', 'update_comment_meta_cache', 'update_comment_post_cache' );

	const EXCLUDING_VARS = array( 'author__not_in', 'comment__not_in', 'parent__not_in', 'post__not_in', 'post_author__not_in', 'type__not_in' );

	const SHAPE_CORE = 'core';

	const SHAPE_NARROWED = 'narrowed';

	private static $coreShape = array();

	private static $tracked = array();

	private static $checks = array();

	private $scan;

	private $sitepress;

	private $wpdb;

	private $store;

	public function __construct( ActivityScan $scan, ?\SitePress $sitepress = null, ?\wpdb $wpdb = null, ?Store $store = null ) {
		$this->scan      = $scan;
		$this->sitepress = $sitepress;
		$this->wpdb      = $wpdb;
		$this->store     = $store;
	}

	public static function resetRequestCache() {
		self::$coreShape = array();
		self::$tracked   = array();
		self::$checks    = array();
	}

	public function noteCoreShape( $query ) {
		if ( ! is_object( $query ) || ! isset( $query->query_vars ) || ! is_array( $query->query_vars ) ) {
			return;
		}

		$id = spl_object_id( $query );
		unset( self::$coreShape[ $id ] );

		$language = $this->dashboardLanguage();
		if ( $language && self::SHAPE_CORE === $this->shapeOf( $query, $language ) ) {
			self::$coreShape[ $id ] = true;
		}
	}

	public function recognise( $query ) {
		if ( ! is_object( $query ) || ! isset( $query->query_vars ) || ! is_array( $query->query_vars ) ) {
			return;
		}

		$id    = spl_object_id( $query );
		$noted = isset( self::$coreShape[ $id ] );
		unset( self::$tracked[ $id ], self::$coreShape[ $id ] );

		$language = $this->dashboardLanguage();
		if ( ! $language ) {
			return;
		}

		$shape = $this->shapeOf( $query, $language );
		if ( null === $shape || ( self::SHAPE_NARROWED === $shape && ! $noted ) ) {
			return;
		}

		$domain                            = $this->domain( $language );
		$query->query_vars['cache_domain'] = $domain;

		self::$tracked[ $id ] = array(
			'language' => $language,
			'status'   => $query->query_vars['status'],
			'needed'   => (int) $query->query_vars['offset'] + (int) $query->query_vars['number'],
			'domain'   => $domain,
			'narrowed' => self::SHAPE_NARROWED === $shape,
		);
	}

	public function noteCoreClauses( $clauses, $query ) {
		if ( is_array( $clauses ) && is_object( $query ) && isset( self::$tracked[ spl_object_id( $query ) ] ) ) {
			self::$tracked[ spl_object_id( $query ) ]['core'] = array(
				'join'  => isset( $clauses['join'] ) ? (string) $clauses['join'] : '',
				'where' => isset( $clauses['where'] ) ? (string) $clauses['where'] : '',
			);
		}

		return $clauses;
	}

	public function restrict( $clauses, $query ) {
		if ( ! is_array( $clauses ) || ! is_object( $query ) ) {
			return $clauses;
		}

		$id = spl_object_id( $query );
		if ( isset( self::$checks[ $id ] ) ) {
			return $this->withIds( $clauses, self::$checks[ $id ] );
		}
		if ( ! isset( self::$tracked[ $id ] ) ) {
			return $clauses;
		}
		$tracked = self::$tracked[ $id ];
		unset( self::$tracked[ $id ] );

		if ( ! isset( $query->query_vars['cache_domain'] ) || $tracked['domain'] !== $query->query_vars['cache_domain'] ) {
			return $clauses;
		}

		$ids = $this->scan->find( $tracked['language'], $tracked['status'], $tracked['needed'] );

		if ( count( $ids ) >= $tracked['needed']
			&& ( $tracked['narrowed'] || self::hasOtherClauses( $clauses, $tracked ) )
			&& ! $this->noneHidden( $ids, $query )
		) {
			$ids = $this->scan->find( $tracked['language'], $tracked['status'], ActivityScan::BATCH_SIZE * ActivityScan::MAX_BATCHES );
		}

		return $this->withIds( $clauses, $ids );
	}

	private function withIds( array $clauses, array $ids ) {
		$wpdb = $this->wpdb();
		$rule = $ids
			? "{$wpdb->comments}.comment_ID IN (" . implode( ',', array_map( 'intval', $ids ) ) . ')'
			: '1 = 0';

		$where            = isset( $clauses['where'] ) ? (string) $clauses['where'] : '';
		$clauses['where'] = '' === trim( $where ) ? $rule : $where . ' AND ' . $rule;

		return $clauses;
	}

	private function noneHidden( array $ids, $query ) {
		$listed = $this->listed( $ids, $query );

		return is_array( $listed ) && ! array_diff( $ids, array_map( 'intval', $listed ) );
	}

	protected function listed( array $ids, $query ) {
		$vars = array_merge(
			$query->query_vars,
			array(
				'fields'        => 'ids',
				'number'        => '',
				'offset'        => '',
				'orderby'       => 'none',
				'no_found_rows' => true,
				'cache_domain'  => self::CHECK_DOMAIN_PREFIX . md5( implode( ',', $ids ) ),
			)
		);

		$check   = new \WP_Comment_Query();
		$checkId = spl_object_id( $check );

		self::$checks[ $checkId ] = $ids;
		try {
			$listed = $check->query( $vars );
		} finally {
			unset( self::$checks[ $checkId ] );
		}

		return is_array( $listed ) ? $listed : null;
	}

	private function shapeOf( $query, $language ) {
		$vars     = $query->query_vars;
		$defaults = isset( $query->query_var_defaults ) && is_array( $query->query_var_defaults ) ? $query->query_var_defaults : array();
		$offset   = isset( $vars['offset'] ) ? $vars['offset'] : '';

		if ( ! $defaults
			|| ! isset( $vars['number'], $vars['status'] )
			|| ! is_numeric( $vars['number'] ) || (int) $vars['number'] <= 0
			|| ! in_array( $vars['status'], array( 'all', 'approve' ), true )
			|| ( '' !== $offset && ( ! is_numeric( $offset ) || (int) $offset < 0 ) )
		) {
			return null;
		}

		$narrowed = false;
		foreach ( $vars as $name => $value ) {
			if ( in_array( $name, self::FREE_VARS, true ) || ( 'cache_domain' === $name && $this->isOwnDomain( $value, $language ) ) ) {
				continue;
			}

			if ( array_key_exists( $name, $defaults ) ) {
				if ( $defaults[ $name ] === $value ) {
					continue;
				}
				if ( ! in_array( $name, self::EXCLUDING_VARS, true ) ) {
					return null;
				}
				$narrowed = $narrowed || ! empty( $value );
			} elseif ( ! empty( $value ) ) {
				$narrowed = true;
			}
		}

		return $narrowed ? self::SHAPE_NARROWED : self::SHAPE_CORE;
	}

	private static function hasOtherClauses( array $clauses, array $tracked ) {
		if ( ! isset( $tracked['core'] ) ) {
			return false;
		}

		$join  = isset( $clauses['join'] ) ? (string) $clauses['join'] : '';
		$where = isset( $clauses['where'] ) ? (string) $clauses['where'] : '';

		return $tracked['core']['join'] !== $join || $tracked['core']['where'] !== $where;
	}

	private function domain( $language ) {
		$domain = self::CACHE_DOMAIN_PREFIX . $language;
		$store  = $this->store ? $this->store->get() : null;

		return null === $store ? $domain : $domain . '_' . $store['built_at'] . '_' . $store['generation'];
	}

	private function isOwnDomain( $value, $language ) {
		$own = self::CACHE_DOMAIN_PREFIX . $language;

		return is_string( $value ) && ( $own === $value || 0 === strpos( $value, $own . '_' ) );
	}

	private function dashboardLanguage() {
		if ( ! $this->isDashboardPageLoad() ) {
			return null;
		}

		$language = $this->currentLanguage();

		return $language && 'all' !== $language ? $language : null;
	}

	private function isDashboardPageLoad() {
		$sitepress = $this->sitepress();

		return $sitepress
			&& $sitepress->get_wp_api()->is_back_end()
			&& 'index.php' === $this->pagenow();
	}

	protected function pagenow() {
		global $pagenow;

		return is_string( $pagenow ) ? $pagenow : null;
	}

	private function currentLanguage() {
		$sitepress = $this->sitepress();

		return $sitepress ? $sitepress->get_current_language() : null;
	}

	private function sitepress() {
		if ( ! $this->sitepress ) {
			global $sitepress;

			$this->sitepress = $sitepress instanceof \SitePress ? $sitepress : null;
		}

		return $this->sitepress;
	}

	private function wpdb() {
		if ( ! $this->wpdb ) {
			global $wpdb;

			$this->wpdb = $wpdb;
		}

		return $this->wpdb;
	}
}
