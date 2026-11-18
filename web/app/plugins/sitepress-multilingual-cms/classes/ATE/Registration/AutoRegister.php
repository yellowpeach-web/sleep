<?php

namespace WPML\TM\ATE\Registration;

use WPML\TM\ATE\AutoTranslate\Endpoint\EnableATE;
use WPML\TM\ATE\Log\Entry;
use WPML\TM\ATE\Log\EventsTypes;
use function WPML\Container\make;

class AutoRegister {

	const TRIGGER_PAGE = 'page';
	const TRIGGER_JOB  = 'job';

	const THROTTLE_KEY = 'wpml_ate_autoreg_attempt';

	const SKIP_LOGGED_KEY_PREFIX = 'wpml_ate_autoreg_skip_';

	const REASON_NO_CAPABILITY = 'no_manage_options';
	const REASON_NO_SITE_KEY   = 'no_site_key';

	const SKIPPED_DESCRIPTION   = 'Automatic Translation registration was not attempted.';
	const COMPLETED_DESCRIPTION = 'Automatic Translation registration completed.';

	public static function attempt( $trigger ) {
		if ( ! \WPML_TM_ATE_Status::is_ate_account_wanted() ) {
			return false;
		}

		if ( self::TRIGGER_PAGE === $trigger && \WPML_TM_ATE_Status::is_active() && self::hasKeys() ) {
			return true;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			self::logSkip( self::REASON_NO_CAPABILITY, $trigger );

			return false;
		}

		if ( get_transient( self::THROTTLE_KEY ) ) {
			return false;
		}

		$sitekeyProvider = make( \WPML\TM\ATE\Sitekey\SitekeyProvider::class );
		if ( ! $sitekeyProvider->hasSitekey() ) {
			self::logSkip( self::REASON_NO_SITE_KEY, $trigger );

			return false;
		}

		set_transient( self::THROTTLE_KEY, 1, 5 * MINUTE_IN_SECONDS );

		try {
			make( EnableATE::class )->enable();
		} catch ( \Throwable $e ) {
			EnableATE::logFailure(
				[
					'trigger'   => $trigger,
					'exception' => get_class( $e ),
				]
			);

			return false;
		}

		$registered = self::hasKeys();
		if ( $registered ) {
			self::log( self::COMPLETED_DESCRIPTION, [ 'trigger' => $trigger ] );
		}

		return $registered;
	}

	public static function attemptForJob() {
		if ( self::hasKeys() ) {
			return false;
		}

		return self::attempt( self::TRIGGER_JOB );
	}

	private static function hasKeys() {
		$auth = make( \WPML_TM_ATE_Authentication::class );

		return (bool) $auth->has_keys();
	}

	private static function logSkip( $reason, $trigger ) {
		$key = self::SKIP_LOGGED_KEY_PREFIX . $reason;
		if ( get_transient( $key ) ) {
			return;
		}
		set_transient( $key, 1, 5 * MINUTE_IN_SECONDS );

		self::log(
			self::SKIPPED_DESCRIPTION,
			[
				'reason'  => $reason,
				'trigger' => $trigger,
			],
			true
		);
	}

	private static function log( $description, array $extraData, $avoidDuplication = false ) {
		$entry              = new Entry();
		$entry->eventType   = EventsTypes::SERVER_AMS;
		$entry->description = $description;
		$entry->extraData   = $extraData;
		wpml_tm_ate_ams_log( $entry, $avoidDuplication );
	}
}
