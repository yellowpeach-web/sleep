<?php

class WPML_Single_Url_Cache_Scheduler {

	const CRON_HOOK       = 'wpml_process_single_url_resolution_queue';
	const AJAX_ACTION     = 'wpml_single_url_resolution_worker';
	const DISPATCH_LOCK   = 'wpml_single_url_resolution_dispatch_lock';
	const SIGNATURE_TTL   = 300;
	const CRON_DELAY      = 60;
	const LOOPBACK_ACTION = 'single-url-cache-worker';

	const REDISPATCH_LIMIT       = 600;
	const REDISPATCH_WINDOW      = 3600;
	const REDISPATCH_STALL_LIMIT = 6;
	const REDISPATCH_STATE       = 'wpml_single_url_resolution_redispatch_state';

	private $shutdown_dispatches = [];

	private $shutdown_registered = false;

	private $scheduled_in_request = [];

	public function schedule( $blog_id = null ) {
		$blog_id = $blog_id ? (int) $blog_id : get_current_blog_id();
		if ( isset( $this->scheduled_in_request[ $blog_id ] ) ) {
			return;
		}
		$this->scheduled_in_request[ $blog_id ] = true;

		$switched = false;

		if ( get_current_blog_id() !== $blog_id ) {
			switch_to_blog( $blog_id );
			$switched = true;
		}

		try {
			if ( ! wp_next_scheduled( self::CRON_HOOK, [ $blog_id ] ) ) {
				wp_schedule_single_event( time() + self::CRON_DELAY, self::CRON_HOOK, [ $blog_id ] );
			}

			if ( false === get_transient( self::DISPATCH_LOCK ) ) {
				set_transient( self::DISPATCH_LOCK, 1, 30 );
				$this->shutdown_dispatches[ $blog_id ] = $blog_id;
			}
		} finally {
			if ( $switched ) {
				restore_current_blog();
			}
		}

		if ( ! $this->shutdown_registered ) {
			$this->shutdown_registered = true;
			add_action( 'shutdown', [ $this, 'dispatch_loopbacks' ], PHP_INT_MAX - 10 );
		}
	}

	public function schedule_recovery( $blog_id, $timestamp ) {
		$blog_id   = (int) $blog_id;
		$timestamp = max( time() + 1, (int) $timestamp );
		$switched  = false;

		if ( get_current_blog_id() !== $blog_id ) {
			switch_to_blog( $blog_id );
			$switched = true;
		}

		try {
			$scheduled = wp_next_scheduled( self::CRON_HOOK, [ $blog_id ] );
			if ( ! $scheduled || $scheduled > $timestamp ) {
				wp_schedule_single_event( $timestamp, self::CRON_HOOK, [ $blog_id ] );
			}
		} finally {
			if ( $switched ) {
				restore_current_blog();
			}
		}
	}

	public function dispatch_loopbacks() {
		foreach ( $this->shutdown_dispatches as $blog_id ) {
			$this->dispatch_loopback( $blog_id );
		}

		$this->shutdown_dispatches = [];
	}

	private function dispatch_loopback( $blog_id ) {
		$timestamp = time();
		$signature = $this->signature( $blog_id, $timestamp );

		if ( ! $signature ) {
			return;
		}

		wp_remote_post(
			get_admin_url( $blog_id, 'admin-ajax.php' ),
			[
				'blocking'    => false,
				'timeout'     => 0.01,
				'redirection' => 0,
				'body'        => [
					'action'    => self::AJAX_ACTION,
					'blog_id'   => $blog_id,
					'timestamp' => $timestamp,
					'signature' => $signature,
				],
			]
		);
	}

	public function signature( $blog_id, $timestamp ) {
		$salt = wp_salt( 'auth' );
		if ( ! is_string( $salt ) || strlen( $salt ) < 32 ) {
			return '';
		}

		return hash_hmac(
			'sha256',
			implode( '|', [ self::LOOPBACK_ACTION, (int) $blog_id, (int) $timestamp ] ),
			$salt
		);
	}

	public function verify( $blog_id, $timestamp, $signature ) {
		if ( abs( time() - (int) $timestamp ) > self::SIGNATURE_TTL ) {
			return false;
		}

		$expected = $this->signature( $blog_id, $timestamp );

		return '' !== $expected
			&& is_string( $signature )
			&& hash_equals( $expected, $signature );
	}

	public function allow_redispatch( $blog_id ) {
		$switched = false;
		if ( get_current_blog_id() !== (int) $blog_id ) {
			switch_to_blog( (int) $blog_id );
			$switched = true;
		}

		try {
			if ( $this->redispatch_allowed() ) {
				delete_transient( self::DISPATCH_LOCK );
			}
		} finally {
			if ( $switched ) {
				restore_current_blog();
			}
		}
	}

	public function note_worker_result( $blog_id, array $result ) {
		if ( ! empty( $result['locked'] ) || ! isset( $result['unfinished'] ) ) {
			return;
		}

		$switched = false;
		if ( get_current_blog_id() !== (int) $blog_id ) {
			switch_to_blog( (int) $blog_id );
			$switched = true;
		}

		try {
			$state      = $this->redispatch_state();
			$unfinished = (int) $result['unfinished'];
			$progress   = ! empty( $result['seeding'] )
				|| ! empty( $result['cleaned'] )
				|| ( null !== $state['unfinished'] && $unfinished < $state['unfinished'] );

			if ( null !== $state['unfinished'] ) {
				$state['streak'] = $progress ? 0 : $state['streak'] + 1;
			}
			$state['unfinished'] = $unfinished;

			$this->save_redispatch_state( $state );
		} finally {
			if ( $switched ) {
				restore_current_blog();
			}
		}
	}

	private function redispatch_allowed() {
		$limit = (int) apply_filters( 'wpml_single_url_cache_redispatch_limit', self::REDISPATCH_LIMIT );
		if ( $limit < 1 ) {
			return false;
		}

		$window = (int) apply_filters( 'wpml_single_url_cache_redispatch_window', self::REDISPATCH_WINDOW );
		$window = max( MINUTE_IN_SECONDS, $window );

		$state = $this->redispatch_state();
		if ( $state['streak'] >= self::REDISPATCH_STALL_LIMIT ) {
			return false;
		}

		$now           = time();
		$state['times'] = array_values(
			array_filter(
				$state['times'],
				function( $t ) use ( $now, $window ) {
					return $t > $now - $window;
				}
			)
		);
		if ( count( $state['times'] ) >= $limit ) {
			return false;
		}

		$state['times'][] = $now;
		$this->save_redispatch_state( $state );

		return true;
	}

	private function redispatch_state() {
		$state = get_transient( self::REDISPATCH_STATE );
		if ( ! is_array( $state ) ) {
			$state = [];
		}

		return [
			'times'      => isset( $state['times'] ) && is_array( $state['times'] )
				? array_map( 'intval', $state['times'] )
				: [],
			'streak'     => isset( $state['streak'] ) ? (int) $state['streak'] : 0,
			'unfinished' => isset( $state['unfinished'] ) ? (int) $state['unfinished'] : null,
		];
	}

	private function save_redispatch_state( array $state ) {
		set_transient( self::REDISPATCH_STATE, $state, self::REDISPATCH_WINDOW );
	}
}
