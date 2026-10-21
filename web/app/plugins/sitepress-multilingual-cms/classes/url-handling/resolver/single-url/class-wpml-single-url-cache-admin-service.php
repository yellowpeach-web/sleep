<?php

class WPML_Single_Url_Cache_Admin_Service {

	const REBUILD_LOCK_OPTION = 'wpml_single_url_resolution_rebuild_admin_lock';
	const REBUILD_LOCK_TTL    = 30;

	private $repository;

	private $generation;

	private $scheduler;

	private $worker;

	public function __construct(
		?WPML_Single_Url_Cache_Repository $repository = null,
		?WPML_Single_Url_Cache_Generation $generation = null,
		?WPML_Single_Url_Cache_Scheduler $scheduler = null,
		?WPML_Single_Url_Cache_Worker $worker = null
	) {
		if ( ! $repository || ! $generation || ! $scheduler || ! $worker ) {
			$services   = WPML_Single_Url_Cache_Services::get_instance();
			$repository = $services->repository();
			$generation = $services->generation();
			$scheduler  = $services->scheduler();
			$worker     = $services->worker();
		}

		$this->repository = $repository;
		$this->generation = $generation;
		$this->scheduler  = $scheduler;
		$this->worker     = $worker;
	}

	public function rebuild() {
		$this->generation->get();
		$active = get_option( WPML_Single_Url_Cache_Worker::REBUILD_OPTION, [] );
		if ( is_array( $active ) && ! empty( $active['active'] ) ) {
			$this->scheduler->schedule( get_current_blog_id() );
			return $this->get_status();
		}

		$lock_token = $this->acquire_rebuild_lock();
		if ( ! $lock_token ) {
			return $this->get_status();
		}

		try {
			$active = get_option( WPML_Single_Url_Cache_Worker::REBUILD_OPTION, [] );
			if ( is_array( $active ) && ! empty( $active['active'] ) ) {
				$this->scheduler->schedule( get_current_blog_id() );
				return $this->get_status();
			}

			$this->generation->reset_request_cache();
			$old_generation = $this->generation->get();
			$new_generation = $this->generation->bump();

			$this->begin_generation_rebuild( $old_generation, $new_generation );

			return $this->get_status();
		} finally {
			$this->release_rebuild_lock( $lock_token );
		}
	}

	public function begin_generation_rebuild( $old_generation, $new_generation ) {
		$current = get_option( WPML_Single_Url_Cache_Worker::REBUILD_OPTION, [] );

		if ( is_array( $current ) && ! empty( $current['active'] ) ) {
			$current_target = isset( $current['new_generation'] )
				? (int) $current['new_generation']
				: (int) $old_generation;

			if ( (int) $new_generation > $current_target ) {
				if ( ! isset( $current['old_generation'] ) ) {
					$current['old_generation'] = (int) $old_generation;
				}
				update_option(
					WPML_Single_Url_Cache_Worker::REBUILD_OPTION,
					WPML_Single_Url_Cache_Worker::extend_rebuild_state( $current, (int) $new_generation ),
					false
				);
			}

			$this->scheduler->schedule( get_current_blog_id() );
			return;
		}

		$source_generation = (int) $old_generation;
		$has_rows          = $this->repository->has_rows( $source_generation );

		update_option(
			WPML_Single_Url_Cache_Worker::REBUILD_OPTION,
			[
				'active'            => $has_rows,
				'old_generation'    => $source_generation,
				'new_generation'    => (int) $new_generation,
				'source_generation' => $source_generation,
				'cursor'            => '',
				'seeded'            => 0,
				'seed_complete'     => ! $has_rows,
				'processed'         => 0,
				'total'             => 0,
				'counted'           => ! $has_rows,
				'started_at'        => current_time( 'mysql', true ),
				'updated_at'        => current_time( 'mysql', true ),
			],
			false
		);

		if ( $has_rows ) {
			$this->scheduler->schedule( get_current_blog_id() );
		}
	}

	public function process_batch() {
		return $this->worker->run( get_current_blog_id() );
	}

	public function get_status() {
		$generation = $this->generation->get();
		$database   = $this->repository->status( $generation );
		$raw_counts = $database['counts'];
		$worker     = get_option( WPML_Single_Url_Cache_Worker::STATUS_OPTION, [] );
		$rebuild    = get_option( WPML_Single_Url_Cache_Worker::REBUILD_OPTION, [] );
		$lock_live  = $this->has_live_rebuild_lock();
		$total      = is_array( $rebuild ) && isset( $rebuild['total'] ) ? (int) $rebuild['total'] : 0;
		$processed  = is_array( $rebuild ) && isset( $rebuild['processed'] )
			? min( $total, (int) $rebuild['processed'] )
			: 0;
		$oldest     = isset( $database['oldest_pending'] ) ? $database['oldest_pending'] : null;

		return [
			'generation'     => $generation,
			'counts'         => [
				'positive'            => (int) $raw_counts[ WPML_Single_Url_Cache_Entry::STATE_POSITIVE ]
					+ (int) $raw_counts[ WPML_Single_Url_Cache_Entry::STATE_ROUTE ],
				'route'               => (int) $raw_counts[ WPML_Single_Url_Cache_Entry::STATE_ROUTE ],
				'missing_translation' => (int) $raw_counts[ WPML_Single_Url_Cache_Entry::STATE_MISSING_TRANSLATION ],
				'negative'            => (int) $raw_counts[ WPML_Single_Url_Cache_Entry::STATE_NEGATIVE ]
					+ (int) $raw_counts[ WPML_Single_Url_Cache_Entry::STATE_MISSING_TRANSLATION ],
				'pending'             => (int) $raw_counts[ WPML_Single_Url_Cache_Entry::STATE_PENDING ],
				'failed'              => (int) $raw_counts[ WPML_Single_Url_Cache_Entry::STATE_FAILED ],
			],
			'oldest_pending' => $this->format_age( $oldest ),
			'last_run'       => is_array( $worker ) && isset( $worker['last_run'] ) ? $worker['last_run'] : null,
			'last_error'     => is_array( $worker ) && ! empty( $worker['last_error'] ) ? $worker['last_error'] : null,
			'rebuild'        => [
				'active'    => ( is_array( $rebuild ) && ! empty( $rebuild['active'] ) ) || $lock_live,
				'stalled'   => is_array( $rebuild ) && ! empty( $rebuild['stalled'] ),
				'processed' => $processed,
				'total'     => $total,
				'percent'   => $total > 0 ? min( 100, (int) round( 100 * $processed / $total ) ) : 0,
			],
			'warnings'       => [
				'stalled'       => $this->is_stalled( $oldest ),
				'cron_disabled' => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
				'table_missing' => empty( $database['available'] ),
			],
		];
	}

	private function format_age( $date ) {
		if ( ! $date ) {
			return null;
		}

		$timestamp = strtotime( $date . ' UTC' );
		if ( ! $timestamp ) {
			return $date;
		}

		return human_time_diff( $timestamp, time() );
	}

	private function is_stalled( $oldest ) {
		if ( ! $oldest ) {
			return false;
		}

		$timestamp = strtotime( $oldest . ' UTC' );
		return $timestamp && $timestamp < time() - 10 * MINUTE_IN_SECONDS;
	}

	private function acquire_rebuild_lock() {
		$token = wp_generate_uuid4();
		$value = [
			'token'      => $token,
			'expires_at' => time() + self::REBUILD_LOCK_TTL,
		];

		if ( add_option( self::REBUILD_LOCK_OPTION, $value, '', 'no' ) ) {
			return $token;
		}

		$existing = get_option( self::REBUILD_LOCK_OPTION, [] );
		if (
			is_array( $existing )
			&& ! empty( $existing['expires_at'] )
			&& (int) $existing['expires_at'] < time()
			&& $this->delete_rebuild_lock_if_matches( $existing )
			&& add_option( self::REBUILD_LOCK_OPTION, $value, '', 'no' )
		) {
			return $token;
		}

		return '';
	}

	private function release_rebuild_lock( $token ) {
		$current = get_option( self::REBUILD_LOCK_OPTION, [] );
		if (
			is_array( $current )
			&& isset( $current['token'] )
			&& hash_equals( (string) $current['token'], (string) $token )
		) {
			$this->delete_rebuild_lock_if_matches( $current );
		}
	}

	private function has_live_rebuild_lock() {
		$lock = get_option( self::REBUILD_LOCK_OPTION, [] );
		return is_array( $lock )
			&& ! empty( $lock['expires_at'] )
			&& (int) $lock['expires_at'] >= time();
	}

	private function delete_rebuild_lock_if_matches( array $expected ) {
		global $wpdb;

		if ( $wpdb instanceof wpdb ) {
			$deleted = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s",
					self::REBUILD_LOCK_OPTION,
					maybe_serialize( $expected )
				)
			);

			if ( 1 === (int) $deleted ) {
				wp_cache_delete( self::REBUILD_LOCK_OPTION, 'options' );
				return true;
			}

			return false;
		}

		if ( get_option( self::REBUILD_LOCK_OPTION, [] ) === $expected ) {
			return delete_option( self::REBUILD_LOCK_OPTION );
		}

		return false;
	}
}
