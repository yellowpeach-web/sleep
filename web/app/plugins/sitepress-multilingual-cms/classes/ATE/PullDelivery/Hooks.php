<?php

namespace WPML\TM\ATE\PullDelivery;

use IWPML_Backend_Action;
use IWPML_DIC_Action;
use IWPML_Frontend_Action;
use IWPML_REST_Action;
use WPML\TM\ATE\Cancel\PendingCancels;
use WPML\TM\ATE\Retry\CollectorCadence;

class Hooks implements IWPML_Backend_Action, IWPML_REST_Action, IWPML_Frontend_Action, IWPML_DIC_Action {

	const HEARTBEAT_KEY = 'wpml_pull_delivery';

	private $pingHandler;

	private $watchdog;

	private $spawner;

	private $pendingJobs;

	private $retryCadence;

	public function __construct(
		PingHandler $pingHandler,
		Watchdog $watchdog,
		Spawner $spawner,
		?PendingJobs $pendingJobs = null,
		?CollectorCadence $retryCadence = null
	) {
		$this->pingHandler  = $pingHandler;
		$this->watchdog     = $watchdog;
		$this->spawner      = $spawner;
		$this->pendingJobs  = $pendingJobs ?: new PendingJobs();
		$this->retryCadence = $retryCadence ?: new CollectorCadence();
	}

	public function add_hooks() {
		add_filter( 'heartbeat_received', [ $this, 'onHeartbeat' ], 10, 2 );
		add_action( 'wpml_tm_ate_jobs_created', [ $this, 'onJobsCreated' ], 10, 1 );
		add_action( 'add_option_wpml_ate_reachable', [ $this, 'onReachabilityRecorded' ], 10, 2 );
		add_action( 'update_option_wpml_ate_reachable', [ $this, 'onReachabilityUpdated' ], 10, 2 );
		add_action( 'admin_init', [ $this, 'runWatchdog' ] );
		add_action( 'wpml_cancel_all_automatic_jobs', [ ParkedJobs::class, 'forget' ], 20 );
		add_action( 'wpml_cancel_all_automatic_jobs', [ $this, 'onJobsCancelled' ], 30 );
	}


	public function onJobsCancelled() {
		State::onJobsCancelled( $this->pendingJobs->summary() );
	}

	public function runWatchdog() {
		if ( wp_doing_ajax() || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
			return;
		}

		$state = $this->watchdog->evaluate();

		$collects = Modes::PUSH_OK !== $state['mode']
			&& State::isCollectionDue( $state )
			&& ! State::isInWebhookQuiet( $state )
			&& State::arrangeCollectionIfDue();

		if ( $collects ) {
			$this->spawner->spawn();
		}

		$this->retryCadence->run();

		PendingCancels::retryOnAdminRequest();
	}

	public function onHeartbeat( $response, $data ) {
		if ( ! is_array( $response ) ) {
			return $response;
		}

		if ( ! Capability::userMayPing() ) {
			return $response;
		}

		if ( ! is_array( $data ) || ! isset( $data[ self::HEARTBEAT_KEY ] ) ) {
			return $response;
		}

		$payload = $data[ self::HEARTBEAT_KEY ];
		$tabId   = is_array( $payload ) && isset( $payload['tabId'] ) ? (string) $payload['tabId'] : '';

		$force = ForceFlag::fromPayload( $payload );
		$wake  = ForceFlag::fromPayload( $payload, 'wake' );

		$response[ self::HEARTBEAT_KEY ] = $this->pingHandler->handle( $tabId, $force, $wake );

		return $response;
	}

	public function onReachabilityRecorded( $option, $value ) {
		State::onReachabilityAsked( '1' === (string) $value );
	}

	public function onReachabilityUpdated( $oldValue, $value ) {
		State::onReachabilityAsked( '1' === (string) $value );
	}

	public function onJobsCreated( $jobs ) {
		$count = is_array( $jobs ) ? count( $jobs ) : 1;

		if ( $count < 1 ) {
			return;
		}

		$state = State::onJobsCreated( $count );

		$state = $this->watchdog->makeFirstContact( $state );

		$this->watchdog->evaluate( $state );
	}
}
