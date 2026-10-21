<?php

class WPML_Single_Url_Cache_Worker {

	const MAX_ITEMS      = 25;
	const MAX_SECONDS    = 8.0;
	const LOCK_OPTION    = 'wpml_single_url_resolution_worker_lock';
	const STATUS_OPTION  = 'wpml_single_url_resolution_worker_status';
	const REBUILD_OPTION = 'wpml_single_url_resolution_rebuild';
	const REBUILD_BATCH  = 100;
	const CLEANUP_BATCH  = 500;

	private $repository;

	private $generation;

	private $scheduler;

	private $cache;

	private $resolver;

	public function __construct(
		WPML_Single_Url_Cache_Repository $repository,
		WPML_Single_Url_Cache_Generation $generation,
		WPML_Single_Url_Cache_Scheduler $scheduler,
		WPML_Single_Url_Resolution_Cache $cache,
		WPML_Resolve_Single_Url $resolver
	) {
		$this->repository = $repository;
		$this->generation = $generation;
		$this->scheduler  = $scheduler;
		$this->cache      = $cache;
		$this->resolver   = $resolver;
	}

	public function run( $blog_id = null ) {
		$blog_id  = $blog_id ? (int) $blog_id : get_current_blog_id();
		$switched = false;

		if ( get_current_blog_id() !== $blog_id ) {
			switch_to_blog( $blog_id );
			$switched = true;
		}

		try {
			$lock_token = $this->acquire_lock();
			if ( ! $lock_token ) {
				$this->scheduler->schedule_recovery(
					$blog_id,
					$this->lock_recovery_timestamp()
				);
				return [
					'processed' => 0,
					'failed'    => 0,
					'locked'    => true,
				];
			}

			try {
				return $this->run_locked();
			} finally {
				$this->release_lock( $lock_token );
			}
		} finally {
			if ( $switched ) {
				restore_current_blog();
			}
		}
	}

	private function run_locked() {
		$started    = microtime( true );
		$generation = $this->generation->get();
		$processed  = 0;
		$failed     = 0;
		$last_error = '';

		$seeding = $this->rebuild_needs_seeding();
		$this->seed_rebuild();

		while ( $processed + $failed < self::MAX_ITEMS ) {
			if ( $processed + $failed > 0 && microtime( true ) - $started >= self::MAX_SECONDS ) {
				break;
			}

			$entries = $this->repository->claim_batch( $generation, 1 );
			if ( ! $entries ) {
				break;
			}
			$entry = reset( $entries );

			try {
				$result  = $this->resolver->resolve_uncached_for_cache(
					$entry['source_url'],
					$entry['target_language'],
					$entry['source_language']
				);
				$outcome = WPML_Single_Url_Cache_Outcome::build( $entry, $result );

				if ( $this->repository->complete( $entry['cache_key'], $entry['lease_token'], $outcome ) ) {
					$stored = $this->repository->get( $entry['cache_key'] );
					if ( $stored ) {
						$this->cache->promote( $stored );
					}
					++$processed;
				}
			} catch ( Throwable $error ) {
				$this->repository->fail(
					$entry['cache_key'],
					$entry['lease_token'],
					isset( $entry['attempt_count'] ) ? (int) $entry['attempt_count'] : 1,
					get_class( $error ),
					isset( $entry['state'] ) ? $entry['state'] : ''
				);
				if (
					isset( $entry['state'] )
					&& in_array(
						$entry['state'],
						[
							WPML_Single_Url_Cache_Entry::STATE_POSITIVE,
							WPML_Single_Url_Cache_Entry::STATE_ROUTE,
						],
						true
					)
				) {
					$stored = $this->repository->get( $entry['cache_key'] );
					if ( $stored ) {
						$this->cache->promote( $stored );
					}
				}
				$last_error = $error->getMessage();
				++$failed;
			}
		}

		$this->update_rebuild_progress( $processed, $failed );
		$seeding_generations = $this->rebuild_seeding_generations();
		$cleanup             = $seeding_generations
			? $this->repository->cleanup_during_rebuild(
				$seeding_generations[0],
				$seeding_generations[1],
				self::CLEANUP_BATCH
			)
			: $this->repository->cleanup( $generation, self::CLEANUP_BATCH );
		if ( $cleanup['cache_keys'] ) {
			$this->cache->delete_object_cache_keys( $cleanup['cache_keys'] );
		}
		$this->store_status( $processed, $failed, $last_error );

		$next_work = $this->repository->next_work_timestamp( $generation );
		if (
			$cleanup['more']
			|| $this->rebuild_needs_seeding()
			|| ( $next_work && $next_work <= time() )
		) {
			$this->scheduler->schedule( get_current_blog_id() );
		} elseif ( $next_work ) {
			$this->scheduler->schedule_recovery( get_current_blog_id(), $next_work );
		}

		return [
			'processed'  => $processed,
			'failed'     => $failed,
			'locked'     => false,
			'unfinished' => (int) $this->repository->count_unfinished( $generation ),
			'seeding'    => $seeding,
			'cleaned'    => count( $cleanup['cache_keys'] ),
		];
	}

	private function seed_rebuild() {
		$state = get_option( self::REBUILD_OPTION, [] );
		if ( ! is_array( $state ) || empty( $state['active'] ) ) {
			return;
		}

		$state = $this->extend_rebuild_to_generation( $state, $this->generation->get() );
		if ( ! empty( $state['seed_complete'] ) ) {
			return;
		}

		$source_generation          = (int) $state['old_generation'];
		$target_generation          = (int) $state['new_generation'];
		$max_rows                   = $this->repository->max_rows();
		$state['source_generation'] = $source_generation;

		if ( empty( $state['counted'] ) ) {
			$state['total']   = min( $max_rows, $this->repository->count_generation( $source_generation ) );
			$state['counted'] = true;
		}

		$seeded = isset( $state['seeded'] ) ? (int) $state['seeded'] : 0;

		if ( $source_generation >= $target_generation || $seeded >= $max_rows ) {
			$state['seed_complete'] = true;
			$state['updated_at']    = current_time( 'mysql', true );
			update_option( self::REBUILD_OPTION, $state, false );
			return;
		}

		$batch = $this->repository->seed_generation(
			$source_generation,
			$target_generation,
			isset( $state['cursor'] ) ? $state['cursor'] : '',
			min( self::REBUILD_BATCH, $max_rows - $seeded )
		);

		$state['seeded'] = $seeded + (int) $batch['inserted'];
		$state['cursor'] = $batch['cursor'];

		if ( $batch['done'] || $state['seeded'] >= $max_rows ) {
			$state['cursor']        = '';
			$state['seed_complete'] = true;
		}

		$state['updated_at'] = current_time( 'mysql', true );
		update_option( self::REBUILD_OPTION, $state, false );
	}

	private function update_rebuild_progress( $processed, $failed ) {
		$state = get_option( self::REBUILD_OPTION, [] );
		if ( ! is_array( $state ) || empty( $state['active'] ) ) {
			return;
		}

		$state               = $this->extend_rebuild_to_generation( $state, $this->generation->get() );
		$state['processed']  = isset( $state['processed'] )
			? (int) $state['processed'] + (int) $processed + (int) $failed
			: (int) $processed + (int) $failed;
		$state['updated_at'] = current_time( 'mysql', true );

		if (
			! empty( $state['seed_complete'] )
			&& ! $this->repository->has_pending( (int) $state['new_generation'] )
		) {
			$state['active'] = false;
			$state['stalled']      = $this->repository->count_unfinished( (int) $state['new_generation'] ) > 0;
			$state['completed_at'] = current_time( 'mysql', true );
		}

		update_option( self::REBUILD_OPTION, $state, false );
	}

	private function extend_rebuild_to_generation( array $state, $target_generation ) {
		$extended = self::extend_rebuild_state( $state, $target_generation );
		if ( $extended !== $state ) {
			update_option( self::REBUILD_OPTION, $extended, false );
		}

		return $extended;
	}

	public static function extend_rebuild_state( array $state, $target_generation ) {
		$target_generation = (int) $target_generation;
		$source_generation = isset( $state['old_generation'] ) ? (int) $state['old_generation'] : 0;
		$current_target    = isset( $state['new_generation'] )
			? (int) $state['new_generation']
			: $source_generation;

		if ( $target_generation <= $current_target ) {
			return $state;
		}

		$counted = ! empty( $state['counted'] );
		if ( ! empty( $state['seed_complete'] ) && $current_target > $source_generation ) {
			$source_generation = $current_target;
			$counted           = false;
		}

		$state['old_generation']    = $source_generation;
		$state['source_generation'] = $source_generation;
		$state['new_generation']    = $target_generation;
		$state['cursor']            = '';
		$state['seeded']            = 0;
		$state['seed_complete']     = false;
		$state['processed']         = 0;
		$state['counted']           = $counted;
		$state['total']             = $counted && isset( $state['total'] ) ? (int) $state['total'] : 0;
		$state['active']            = true;
		$state['updated_at']        = current_time( 'mysql', true );

		return $state;
	}

	private function rebuild_needs_seeding() {
		$state = get_option( self::REBUILD_OPTION, [] );
		return is_array( $state )
			&& ! empty( $state['active'] )
			&& empty( $state['seed_complete'] );
	}

	private function rebuild_seeding_generations() {
		if ( ! $this->rebuild_needs_seeding() ) {
			return null;
		}

		$state = get_option( self::REBUILD_OPTION, [] );

		return [
			(int) $state['old_generation'],
			isset( $state['new_generation'] ) ? (int) $state['new_generation'] : (int) $state['old_generation'],
		];
	}

	private function store_status( $processed, $failed, $last_error ) {
		update_option(
			self::STATUS_OPTION,
			[
				'last_run'   => current_time( 'mysql', true ),
				'last_error' => $last_error,
				'processed'  => (int) $processed,
				'failed'     => (int) $failed,
			],
			false
		);
	}

	private function acquire_lock() {
		$token = wp_generate_uuid4();
		$value = [
			'token'      => $token,
			'expires_at' => time() + 10 * MINUTE_IN_SECONDS,
		];

		if ( add_option( self::LOCK_OPTION, $value, '', 'no' ) ) {
			return $token;
		}

		$existing = get_option( self::LOCK_OPTION, [] );
		if (
			is_array( $existing )
			&& ! empty( $existing['expires_at'] )
			&& (int) $existing['expires_at'] < time()
			&& $this->delete_lock_if_matches( $existing )
			&& add_option( self::LOCK_OPTION, $value, '', 'no' )
		) {
			return $token;
		}

		return '';
	}

	private function lock_recovery_timestamp() {
		$existing = get_option( self::LOCK_OPTION, [] );
		if ( is_array( $existing ) && ! empty( $existing['expires_at'] ) ) {
			return max( time() + 1, (int) $existing['expires_at'] );
		}

		return time() + MINUTE_IN_SECONDS;
	}

	private function release_lock( $token ) {
		$current = get_option( self::LOCK_OPTION, [] );
		if ( is_array( $current ) && isset( $current['token'] ) && hash_equals( $current['token'], $token ) ) {
			$this->delete_lock_if_matches( $current );
		}
	}

	private function delete_lock_if_matches( array $expected ) {
		global $wpdb;

		if ( $wpdb instanceof wpdb ) {
			$deleted = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s",
					self::LOCK_OPTION,
					maybe_serialize( $expected )
				)
			);

			if ( 1 === (int) $deleted ) {
				wp_cache_delete( self::LOCK_OPTION, 'options' );
				return true;
			}

			return false;
		}

		if ( get_option( self::LOCK_OPTION, [] ) === $expected ) {
			return delete_option( self::LOCK_OPTION );
		}

		return false;
	}
}
