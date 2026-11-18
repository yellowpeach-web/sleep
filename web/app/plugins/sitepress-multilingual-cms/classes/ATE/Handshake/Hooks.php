<?php

namespace WPML\TM\ATE\Handshake;

use IWPML_Backend_Action;
use IWPML_DIC_Action;
use IWPML_Frontend_Action;
use IWPML_REST_Action;
use WPML\TM\ATE\Cancel\PendingCancels;

class Hooks implements IWPML_Backend_Action, IWPML_Frontend_Action, IWPML_REST_Action, IWPML_DIC_Action {

	const ADMIN_RUN_EVERY = 60;

	const ADMIN_STAMP_EVERY = 60;

	private $retryCadence;

	public function __construct( ?\WPML\TM\ATE\Retry\CollectorCadence $retryCadence = null ) {
		$this->retryCadence = $retryCadence;
	}

	public function add_hooks() {
		Acceptance::addHooks();
		ReportHooks::addHooks();

		add_action( 'rest_api_init', [ $this, 'registerRoutes' ] );
		add_action( 'admin_init', [ $this, 'onAdminLoad' ] );
		add_action( 'wpml_tm_ate_jobs_created', [ $this, 'onJobsCreated' ], 10, 1 );
		add_action( 'template_redirect', [ $this, 'onFrontLoad' ] );
		add_action( CronTick::HOOK, [ CronTick::class, 'onTick' ] );
		add_action( 'admin_init', [ $this, 'runAdminChores' ] );
		add_action( 'wpml_cancel_all_automatic_jobs', [ \WPML\TM\ATE\PullDelivery\ParkedJobs::class, 'forget' ], 20 );
		add_action( PendingCancels::OWED_FOR_RUN_ACTION, [ Runner::class, 'startForOwed' ] );
		add_action( 'admin_init', [ Corrections\Offer::class, 'onAdminLoad' ] );
	}

	public function runAdminChores() {
		if ( wp_doing_ajax() || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
			return;
		}

		( $this->retryCadence ?: new \WPML\TM\ATE\Retry\CollectorCadence() )->run();
		\WPML\TM\ATE\Cancel\PendingCancels::retryOnAdminRequest();
	}

	public function registerRoutes() {
		( new Routes() )->add_hooks();
	}

	public function onAdminLoad() {
		if ( wp_doing_ajax() || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
			return;
		}

		$now   = Clock::now();
		$state = State::get();

		if ( (int) $state['last_admin_load_at'] < $now - self::ADMIN_STAMP_EVERY ) {
			$state = State::update( [ 'last_admin_load_at' => $now ] );
		}

		$state = Gate::withFreshInFlight( $state );

		$hasWork = (int) $state['in_flight'] > 0 || PendingCancels::owedForRun() > 0;

		if ( ! $hasWork || (int) $state['last_run_at'] >= $now - self::ADMIN_RUN_EVERY ) {
			if ( self::isGetRequest() ) {
				CreditResume::afterAdminLoad();
			}

			return;
		}

		if ( ! self::isGetRequest() && ! Plan::phpFinishesEarly() ) {
			return;
		}

		State::update( [ 'last_run_at' => $now, 'last_run_trigger' => Runner::TRIGGER_ADMIN ] );
		Runner::spawn( Runner::TRIGGER_ADMIN );
		CronTick::reconcile();
	}

	private static function isGetRequest() {
		return isset( $_SERVER['REQUEST_METHOD'] ) && 'GET' === $_SERVER['REQUEST_METHOD'];
	}

	public function onJobsCreated( $jobs ) {
		$count = is_array( $jobs ) ? count( $jobs ) : 1;

		if ( $count < 1 ) {
			return;
		}

		$state   = State::get();
		$changes = [
			'in_flight'    => (int) $state['in_flight'] + $count,
			'in_flight_at' => Clock::now(),
		];

		if ( ! (int) $state['sent_uncollected_since'] ) {
			$changes['sent_uncollected_since'] = Clock::now();
		}

		State::update( $changes );
		CronTick::reconcile();
	}

	public function onFrontLoad() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		$state = State::get();

		if ( (int) $state['sent_uncollected_since'] < 1 ) {
			return;
		}

		$since = Clock::now() - Gate::seconds();

		if ( (int) $state['last_admin_load_at'] >= $since || (int) $state['last_run_at'] >= $since ) {
			return;
		}

		if ( ! Gate::open( 'front' ) ) {
			return;
		}

		State::update(
			[
				'last_front_poll_at' => Clock::now(),
				'last_run_at'        => Clock::now(),
				'last_run_trigger'   => Runner::TRIGGER_FRONT,
			]
		);

		Runner::spawn( Runner::TRIGGER_FRONT );
	}
}
