<?php

namespace WPML\TM\ATE\TranslateEverything;

use WPML\TM\ATE\AutomaticTranslationCapabilities;
use WPML\TM\AutomaticTranslation\Actions\Actions;
use WPML\TM\Jobs\JobLog;
use function WPML\Container\make;

class JoblessStatusRecovery implements \IWPML_Backend_Action, \IWPML_Frontend_Action {

	const EVENT    = 'wpml_tm_tea_jobless_status_recovery';
	const SCHEDULE = 'hourly';

	const MAX_PAIRS_PER_PASS = 20;

	public function add_hooks() {
		add_action( 'init', [ $this, 'schedule' ] );
		add_action( self::EVENT, [ $this, 'run' ] );
		add_action( 'wpml_deactivated', [ $this, 'unschedule' ] );
		add_action( 'wpml_reset_plugins_before', [ $this, 'unschedule' ] );
	}

	public function schedule() {
		if ( ! wp_next_scheduled( self::EVENT ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, self::SCHEDULE, self::EVENT );
		}
	}

	public static function unschedule() {
		wp_clear_scheduled_hook( self::EVENT );
	}

	public function run() {
		$ownsRequest = JobLog::maybeInitBackgroundRequest( 'wpml-tea-jobless-status-recovery' );
		JobLog::createNewGroup( JobLog::GROUP_ID_TRANSLATE_EVERYTHING, 'Translate Everything: pairs left with a status and no job' );
		try {
			$this->recover();
		} catch ( \Throwable $error ) {
			JobLog::addError( 'tea_jobless_status_recovery_failed', [ 'error' => get_class( $error ) ] );
		} finally {
			JobLog::finishCurrentGroup();
			if ( $ownsRequest ) {
				JobLog::flushBackgroundRequest();
			}
		}
	}

	public function recover() {
		if ( ! \WPML_TM_ATE_Status::is_account_usable()
			 || ! AutomaticTranslationCapabilities::shouldTranslateEverythingFresh() ) {
			return [];
		}

		$untranslatedPosts = make( UntranslatedPosts::class );
		$byType            = $untranslatedPosts->getJoblessElementsToProcess(
			$untranslatedPosts->getEligibleLanguageCodes( true ),
			self::MAX_PAIRS_PER_PASS
		);
		if ( ! $byType ) {
			return [];
		}

		$actions = make( Actions::class );
		$sent    = [];
		foreach ( $byType as $type => $rows ) {
			JobLog::add( 'tea_jobless_status_resent', [ 'type' => $type, 'pairs' => $rows ] );
			$untranslatedPosts->createTranslationJobs( $actions, $rows, $type );
			$sent[ $type ] = array_values( array_unique( array_map( 'intval', array_column( $rows, 0 ) ) ) );
		}

		return $sent;
	}
}
