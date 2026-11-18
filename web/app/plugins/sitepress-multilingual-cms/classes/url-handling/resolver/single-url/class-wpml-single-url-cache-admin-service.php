<?php

class WPML_Single_Url_Cache_Admin_Service {

	const REBUILD_LOCK_OPTION = 'wpml_single_url_resolution_rebuild_admin_lock';
	const REBUILD_LOCK_TTL    = 30;

	const CHANGE_LOG_OPTION = 'wpml_single_url_resolution_generation_log';
	const CHANGE_LOG_SIZE   = 50;

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

			$this->record_generation_change( $old_generation, $new_generation, 'troubleshooting_rebuild' );
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
			$this->repository->delete_superseded( (int) $new_generation );
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

		$this->repository->delete_superseded( (int) $new_generation );
	}

	public function record_generation_change( $old_generation, $new_generation, $reason, $context = null ) {
		$context = is_array( $context ) ? $context : self::change_context();
		$log     = $this->generation_changes();

		array_unshift(
			$log,
			[
				'time'    => current_time( 'mysql', true ),
				'from'    => (int) $old_generation,
				'to'      => (int) $new_generation,
				'reason'  => (string) $reason,
				'hook'    => isset( $context['hook'] ) ? (string) $context['hook'] : '',
				'request' => isset( $context['request'] ) ? (string) $context['request'] : '',
				'source'  => isset( $context['source'] ) ? (string) $context['source'] : '',
			]
		);

		update_option( self::CHANGE_LOG_OPTION, array_slice( $log, 0, self::CHANGE_LOG_SIZE ), false );
	}

	public function generation_changes() {
		$log = get_option( self::CHANGE_LOG_OPTION, [] );

		return is_array( $log ) ? array_values( array_filter( $log, 'is_array' ) ) : [];
	}

	public static function change_context() {
		global $wp_current_filter;

		$hooks = array_values(
			array_filter(
				is_array( $wp_current_filter ) ? $wp_current_filter : [],
				function( $hook ) {
					return 'wpml_single_url_resolution_cache_generation_changed' !== $hook;
				}
			)
		);
		$hook  = $hooks ? (string) reset( $hooks ) : '';
		if ( count( $hooks ) > 1 ) {
			$hook .= ' > ' . (string) end( $hooks );
		}

		return [
			'hook'    => $hook,
			'request' => self::request_kind(),
			'source'  => self::calling_component(),
		];
	}

	private static function request_kind() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return 'cli';
		}
		if ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) {
			return 'cron';
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return 'rest';
		}
		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
			return 'ajax';
		}

		return function_exists( 'is_admin' ) && is_admin() ? 'admin' : 'front';
	}

	private static function calling_component() {
		$normalize = function( $path ) {
			return rtrim( str_replace( '\\', '/', (string) $path ), '/' ) . '/';
		};
		$roots     = [];
		if ( defined( 'WPMU_PLUGIN_DIR' ) ) {
			$roots['mu-plugin'] = $normalize( WPMU_PLUGIN_DIR );
		}
		if ( defined( 'WP_PLUGIN_DIR' ) ) {
			$roots['plugin'] = $normalize( WP_PLUGIN_DIR );
		}
		$theme_root = function_exists( 'get_theme_root' ) ? get_theme_root() : '';
		if ( is_string( $theme_root ) && '' !== $theme_root ) {
			$roots['theme'] = $normalize( $theme_root );
		}
		$roots = array_filter(
			$roots,
			function( $root ) {
				return '/' !== $root;
			}
		);
		if ( ! $roots ) {
			return '';
		}

		$own = $normalize( __DIR__ );
		foreach ( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 40 ) as $frame ) {
			$file = isset( $frame['file'] ) ? str_replace( '\\', '/', (string) $frame['file'] ) : '';
			if ( '' === $file || 0 === strpos( $file, $own ) ) {
				continue;
			}

			foreach ( $roots as $kind => $root ) {
				if ( 0 === strpos( $file, $root ) ) {
					$relative = substr( $file, strlen( $root ) );
					$parts    = explode( '/', $relative );

					return $kind . ':' . $parts[0];
				}
			}
		}

		return 'core';
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
			'generation_changes' => $this->generation_changes(),
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
