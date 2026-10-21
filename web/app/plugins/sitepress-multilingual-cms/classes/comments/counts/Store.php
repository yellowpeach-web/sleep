<?php

namespace WPML\Comments\Counts;

class Store {

	const OPTION = 'wpml_comment_counts';

	const SCHEMA = 1;

	const MARK_ACTION = 'wpml_comment_counts_mark_out_of_date';

	const MAX_SWAP_RETRIES = 3;

	private static $memo = array();

	private static $deferred = array();

	private static $deferredMarkAdded = false;

	private $wpdb;

	private $scheduler;

	public function __construct( \wpdb $wpdb, ?Scheduler $scheduler = null ) {
		$this->wpdb      = $wpdb;
		$this->scheduler = $scheduler;
	}

	public static function resetRequestCache() {
		self::$memo              = array();
		self::$deferred          = array();
		self::$deferredMarkAdded = false;
	}

	public static function isValid( $value ) {
		return is_array( $value )
			&& isset( $value['schema'], $value['counts'], $value['built_at'], $value['out_of_date'], $value['generation'] )
			&& self::SCHEMA === $value['schema']
			&& is_array( $value['counts'] )
			&& is_int( $value['built_at'] ) && $value['built_at'] > 0
			&& is_int( $value['out_of_date'] ) && $value['out_of_date'] >= 0
			&& is_int( $value['generation'] ) && $value['generation'] >= 0;
	}

	public function get() {
		$blog_id = get_current_blog_id();

		if ( ! array_key_exists( $blog_id, self::$memo ) ) {
			$value                  = get_option( self::OPTION );
			self::$memo[ $blog_id ] = self::isValid( $value ) ? $value : null;
		}

		return self::$memo[ $blog_id ];
	}

	public function getForWrite() {
		$blog_id = get_current_blog_id();

		if ( array_key_exists( $blog_id, self::$memo ) && null === self::$memo[ $blog_id ] ) {
			$fresh = $this->readFresh();
			if ( null !== $fresh['store'] ) {
				$this->refreshObjectCache( (string) $fresh['raw'] );
			}
			self::$memo[ $blog_id ] = $fresh['store'];
		}

		return $this->get();
	}

	public function readFresh() {
		$wpdb = $this->wpdb;
		$raw = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
				self::OPTION
			)
		);

		$raw   = null === $raw ? null : (string) $raw;
		$value = null === $raw ? null : maybe_unserialize( $raw );

		return array(
			'raw'   => $raw,
			'store' => self::isValid( $value ) ? $value : null,
		);
	}

	private function readForMark() {
		$wpdb = $this->wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name IN (%s, %s)",
				self::OPTION,
				Recount::LOCK_OPTION
			),
			ARRAY_A
		);

		$raw     = null;
		$running = false;
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			if ( self::OPTION === $row['option_name'] ) {
				$raw = (string) $row['option_value'];
			} elseif ( Recount::LOCK_OPTION === $row['option_name'] ) {
				$running = true;
			}
		}
		$value = null === $raw ? null : maybe_unserialize( $raw );

		return array(
			'raw'             => $raw,
			'store'           => self::isValid( $value ) ? $value : null,
			'recount_running' => $running,
		);
	}

	public function compareAndSwap( $old_raw, array $new ) {
		if ( null === $old_raw ) {
			$written = (bool) add_option( self::OPTION, $new, '', false );
		} else {
			$new_raw = maybe_serialize( $new );
			$wpdb    = $this->wpdb;
			$changed = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s",
					$new_raw,
					self::OPTION,
					$old_raw
				)
			);
			$written = 1 === $changed;

			if ( $written ) {
				$this->refreshObjectCache( $new_raw );
			}
		}

		if ( $written ) {
			self::$memo[ get_current_blog_id() ] = $new;
		}

		return $written;
	}

	private function refreshObjectCache( $raw ) {
		$notoptions = wp_cache_get( 'notoptions', 'options' );
		if ( is_array( $notoptions ) && isset( $notoptions[ self::OPTION ] ) ) {
			unset( $notoptions[ self::OPTION ] );
			wp_cache_set( 'notoptions', $notoptions, 'options' );
		}

		$alloptions = wp_cache_get( 'alloptions', 'options', true );
		if ( is_array( $alloptions ) && isset( $alloptions[ self::OPTION ] ) ) {
			$alloptions[ self::OPTION ] = $raw;
			wp_cache_set( 'alloptions', $alloptions, 'options' );
		} else {
			wp_cache_set( self::OPTION, $raw, 'options' );
		}
	}

	public function delete() {
		delete_option( self::OPTION );
		self::$memo[ get_current_blog_id() ] = null;
	}

	public function markOutOfDate() {
		$this->mark( false );
	}

	public function markOutsideChange() {
		$this->mark( true );
	}

	public function postHasComments( $post_id ) {
		$wpdb = $this->wpdb;
		$found = $wpdb->get_var(
			$wpdb->prepare( "SELECT 1 FROM {$wpdb->comments} WHERE comment_post_ID = %d LIMIT 1", $post_id )
		);

		return null !== $found || '' !== $wpdb->last_error;
	}

	private function mark( $move_generation ) {
		$blog_id = get_current_blog_id();
		$store   = null;

		for ( $retry = 0; $retry <= self::MAX_SWAP_RETRIES; $retry++ ) {
			$fresh                  = $this->readForMark();
			$store                  = $fresh['store'];
			self::$memo[ $blog_id ] = $store;

			if ( null === $store ) {
				return;
			}

			if ( $store['out_of_date'] > 0 && ! $fresh['recount_running'] && ! $move_generation ) {
				$this->scheduler()->ensureScheduled( $store );

				return;
			}

			$new = $store;
			if ( 0 === $store['out_of_date'] ) {
				$new['out_of_date'] = time();
			}
			$new['generation'] = $store['generation'] + 1;

			if ( $this->compareAndSwap( $fresh['raw'], $new ) ) {
				$this->scheduler()->ensureScheduled( $new );

				return;
			}
		}

		$this->scheduler()->ensureScheduled( $store );
	}

	public function markDeferred() {
		$blog_id = get_current_blog_id();
		$now     = time();
		if ( isset( self::$deferred[ $blog_id ] ) && $now - self::$deferred[ $blog_id ] < Scheduler::MIN_INTERVAL ) {
			return;
		}
		self::$deferred[ $blog_id ] = $now;

		if ( ! self::$deferredMarkAdded ) {
			self::$deferredMarkAdded = true;
			add_action( 'wp_update_comment_count', array( $this, 'commentCountUpdated' ), 10, 0 );
			add_action( 'shutdown', array( $this, 'markDeferredChanges' ), 10, 0 );
		}

		$this->markOutOfDate();
	}

	public function commentCountUpdated() {
		if ( ! self::$deferred || wp_defer_comment_counting() ) {
			return;
		}

		$this->markDeferredChanges();
	}

	public function markDeferredChanges() {
		$blog_ids       = array_keys( self::$deferred );
		self::$deferred = array();

		foreach ( $blog_ids as $blog_id ) {
			$switch = (int) $blog_id !== get_current_blog_id();
			if ( $switch ) {
				switch_to_blog( (int) $blog_id );
			}

			$this->markOutOfDate();

			if ( $switch ) {
				restore_current_blog();
			}
		}
	}

	public static function markOutOfDateIfOn() {
		if ( ! FeatureSwitch::isLoadedAndOn() ) {
			return;
		}

		global $wpdb;

		( new self( $wpdb ) )->markOutsideChange();
	}

	public function applyDeltas( $language, array $deltas ) {
		return $this->applyDeltaList( array( array( $language, $deltas ) ) );
	}

	public function applyDeltaList( array $changes, $mark_too = false ) {
		$store = $this->get();
		if ( null === $store ) {
			return false;
		}

		$new             = $store;
		$missed_a_change = (bool) $mark_too;
		foreach ( $changes as $change ) {
			list( $language, $deltas ) = $change;

			$buckets = Exclusions::emptyBuckets();
			if ( isset( $new['counts'][ $language ] ) && is_array( $new['counts'][ $language ] ) ) {
				foreach ( $buckets as $bucket => $zero ) {
					if ( isset( $new['counts'][ $language ][ $bucket ] ) ) {
						$buckets[ $bucket ] = (int) $new['counts'][ $language ][ $bucket ];
					}
				}
			}

			foreach ( $deltas as $bucket => $delta ) {
				if ( ! array_key_exists( $bucket, $buckets ) ) {
					continue;
				}
				$buckets[ $bucket ] += (int) $delta;
				if ( $buckets[ $bucket ] < 0 ) {
					$buckets[ $bucket ] = 0;
					$missed_a_change    = true;
				}
			}

			$new['counts'][ $language ] = $buckets;
		}

		$new['generation'] = $store['generation'] + 1;
		if ( $missed_a_change && 0 === $store['out_of_date'] ) {
			$new['out_of_date'] = time();
		}

		if ( $this->compareAndSwap( maybe_serialize( $store ), $new ) ) {
			if ( $missed_a_change ) {
				$this->scheduler()->ensureScheduled( $new );
			}

			return true;
		}

		$this->markOutOfDate();

		return false;
	}

	public function writeRecountResult( array $counts, $generation_read ) {
		for ( $retry = 0; $retry <= self::MAX_SWAP_RETRIES; $retry++ ) {
			$fresh = $this->readFresh();
			$store = $fresh['store'];
			$now   = time();

			if ( null === $store ) {
				$out_of_date = $now;
				$base        = null === $generation_read ? 0 : (int) $generation_read;
			} else {
				if ( $store['generation'] === $generation_read ) {
					$out_of_date = 0;
				} else {
					$out_of_date = $store['out_of_date'] > 0 ? $store['out_of_date'] : $now;
				}
				$base = $store['generation'];
			}

			$new = array(
				'schema'      => self::SCHEMA,
				'counts'      => $counts,
				'built_at'    => $now,
				'out_of_date' => $out_of_date,
				'generation'  => $base + 1,
			);

			if ( $this->compareAndSwap( $fresh['raw'], $new ) ) {
				return $new;
			}
		}

		return null;
	}

	public function writeErrorState() {
		for ( $retry = 0; $retry <= self::MAX_SWAP_RETRIES; $retry++ ) {
			$fresh = $this->readFresh();
			$store = $fresh['store'];

			if ( null === $store || $store['out_of_date'] > 0 ) {
				return;
			}

			$new                = $store;
			$new['out_of_date'] = time();
			$new['generation']  = $store['generation'] + 1;

			if ( $this->compareAndSwap( $fresh['raw'], $new ) ) {
				return;
			}
		}
	}

	private function scheduler() {
		if ( ! $this->scheduler ) {
			$this->scheduler = new Scheduler();
		}

		return $this->scheduler;
	}
}
