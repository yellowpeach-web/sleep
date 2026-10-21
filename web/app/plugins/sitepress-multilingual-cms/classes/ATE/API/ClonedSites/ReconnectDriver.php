<?php

namespace WPML\TM\ATE\ClonedSites;

use WPML\TM\ATE\ClonedSites\AutoMigration\Handler as AutoMigrationHandler;

class ReconnectDriver implements \IWPML_Backend_Action, \IWPML_Frontend_Action, \IWPML_DIC_Action {

	const CRON_HOOK = 'wpml_ate_reconnect_attempt';

	private $autoMigrationHandler;

	private $probe;

	private static $attemptedThisRequest = false;

	public function __construct( AutoMigrationHandler $autoMigrationHandler, ?ConnectionProbe $probe = null ) {
		$this->autoMigrationHandler = $autoMigrationHandler;
		$this->probe                = $probe ?: new ConnectionProbe();
	}

	public function add_hooks() {
		add_action( 'admin_init', [ $this, 'run' ], 999 );
		add_action( self::CRON_HOOK, [ $this, 'runCron' ] );
	}

	public function run() {
		$this->attempt();
	}

	public function runCron() {
		if ( ReconnectState::get() && ReconnectState::isNoAnswer() ) {
			if ( ReconnectState::openedByCreate() ) {
				self::disarm();

				return;
			}

			if ( self::$attemptedThisRequest ) {
				return;
			}
			self::$attemptedThisRequest = true;

			$this->probe->run();

			if ( ReconnectState::get() ) {
				self::arm();
			} else {
				self::disarm();
			}

			return;
		}

		$this->attempt();
	}

	public function attempt() {
		if ( self::$attemptedThisRequest ) {
			return false;
		}

		$state = ReconnectState::get();

		if ( ! $state ) {
			$this->unschedule();

			return true;
		}

		if ( ! ReconnectState::isReconnecting() ) {
			if ( ! \WPML_TM_ATE_Status::is_enabled() && ReconnectState::isNoAnswer() ) {
				ReconnectState::clear();
			}

			$this->unschedule();

			return true;
		}

		if ( ReconnectState::isNoAnswer() ) {
			self::arm();

			return false;
		}

		if ( ! ReconnectState::isDue() ) {
			self::arm();

			return false;
		}

		self::$attemptedThisRequest = true;

		$oldUrl = isset( $state['old_url'] ) ? (string) $state['old_url'] : '';
		$newUrl = isset( $state['new_url'] ) ? (string) $state['new_url'] : '';

		$migrated = ApiCommunication::whileHandlingIdentity(
			function () use ( $oldUrl, $newUrl ) {
				return $this->autoMigrationHandler->tryMigrate( $oldUrl, $newUrl );
			}
		);

		if ( $migrated ) {
			if ( ! ReconnectState::clearUnlessAbout( $oldUrl, $newUrl, $state ) ) {
				self::arm();

				return false;
			}

			$this->unschedule();

			return true;
		}

		$reason   = $this->autoMigrationHandler->getLastFailureReason();
		$errorKey = 'reconnect_attempt_failed' . ( '' !== $reason ? ':' . $reason : '' );

		ReconnectState::recordFailure( $oldUrl, $newUrl, 0, $errorKey );
		self::arm();

		return false;
	}

	public static function arm() {
		if ( ReconnectState::openedByCreate() ) {
			self::disarm();

			return;
		}

		if ( ! function_exists( 'wp_schedule_single_event' ) || ! function_exists( 'wp_next_scheduled' ) ) {
			return;
		}

		$due = ReconnectState::cronDueAt();

		if ( ! $due ) {
			return;
		}

		$scheduled = wp_next_scheduled( self::CRON_HOOK );

		if ( $scheduled === $due ) {
			return;
		}

		if ( $scheduled ) {
			wp_unschedule_event( $scheduled, self::CRON_HOOK );
		}

		wp_schedule_single_event( $due, self::CRON_HOOK );
	}

	public static function disarm() {
		if ( ! function_exists( 'wp_next_scheduled' ) ) {
			return;
		}

		$scheduled = wp_next_scheduled( self::CRON_HOOK );

		if ( $scheduled ) {
			wp_unschedule_event( $scheduled, self::CRON_HOOK );
		}
	}

	private function unschedule() {
		self::disarm();
	}

	public static function resetRequestGuard() {
		self::$attemptedThisRequest = false;
	}
}
