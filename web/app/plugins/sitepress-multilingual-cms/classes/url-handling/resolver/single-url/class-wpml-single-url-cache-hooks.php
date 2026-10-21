<?php

class WPML_Single_Url_Cache_Hooks implements IWPML_Action {

	private $scheduler;

	private $worker;

	private $admin;

	private $generation;

	public function __construct(
		WPML_Single_Url_Cache_Scheduler $scheduler,
		WPML_Single_Url_Cache_Worker $worker,
		WPML_Single_Url_Cache_Admin_Service $admin,
		WPML_Single_Url_Cache_Generation $generation
	) {
		$this->scheduler  = $scheduler;
		$this->worker     = $worker;
		$this->admin      = $admin;
		$this->generation = $generation;
	}

	public function add_hooks() {
		add_action( WPML_Single_Url_Cache_Scheduler::CRON_HOOK, [ $this, 'run_cron' ], 10, 1 );
		$scheduler = $this->scheduler;
		\WPML\Request\Adapter\Ajax::register(
			WPML_Single_Url_Cache_Scheduler::AJAX_ACTION,
			\WPML\Request\Policy\Policy::machine(
				function () use ( $scheduler ) {
					$blog_id   = isset( $_POST['blog_id'] ) ? absint( $_POST['blog_id'] ) : 0;
					$timestamp = isset( $_POST['timestamp'] ) ? absint( $_POST['timestamp'] ) : 0;
					$signature = isset( $_POST['signature'] ) && is_string( $_POST['signature'] ) ? sanitize_text_field( wp_unslash( $_POST['signature'] ) ) : '';

					return $blog_id > 0 && true === $scheduler->verify( $blog_id, $timestamp, $signature );
				},
				'single-URL cache loopback worker: HMAC-SHA256 signature over blog id + timestamp (WPML_Single_Url_Cache_Scheduler::verify)'
			),
			[ $this, 'run_loopback' ]
		);
		add_action( 'wpml_flush_single_url_resolution_cache', [ $this, 'flush' ] );
		add_action(
			'wpml_single_url_resolution_cache_generation_changed',
			[ $this, 'generation_changed' ],
			10,
			3
		);

		$this->generation->synchronize_algorithm_version();
	}

	public function run_cron( $blog_id ) {
		$result = $this->worker->run( (int) $blog_id );
		if ( is_array( $result ) ) {
			$this->scheduler->note_worker_result( (int) $blog_id, $result );
		}
	}

	public function run_loopback() {
		$blog_id   = isset( $_POST['blog_id'] ) ? absint( $_POST['blog_id'] ) : 0;
		$timestamp = isset( $_POST['timestamp'] ) ? absint( $_POST['timestamp'] ) : 0;
		$signature = isset( $_POST['signature'] )
			? sanitize_text_field( wp_unslash( $_POST['signature'] ) )
			: '';

		if ( ! $blog_id || ! $this->scheduler->verify( $blog_id, $timestamp, $signature ) ) {
			status_header( 403 );
			wp_die( 'Invalid URL cache worker signature.' );
		}

		$this->scheduler->allow_redispatch( $blog_id );
		$result = $this->worker->run( $blog_id );
		if ( is_array( $result ) ) {
			$this->scheduler->note_worker_result( $blog_id, $result );
		}
		wp_die( '' );
	}

	public function flush() {
		$this->admin->rebuild();
	}

	public function generation_changed( $old_generation, $new_generation, $reason = '' ) {
		$this->admin->begin_generation_rebuild( (int) $old_generation, (int) $new_generation );
	}
}
