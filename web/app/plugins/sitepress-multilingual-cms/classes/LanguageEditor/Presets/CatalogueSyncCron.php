<?php

namespace WPML\LanguageEditor\Presets;

class CatalogueSyncCron implements \IWPML_Backend_Action, \IWPML_Frontend_Action {

	const EVENT    = 'wpml_language_catalogue_sync';
	const SCHEDULE = 'daily';

	const REPAIR_EVENT = 'wpml_language_catalogue_synced';

	public function add_hooks() {
		add_action( 'init', array( $this, 'schedule' ) );
		add_action( self::EVENT, array( $this, 'run' ) );
		add_action( self::REPAIR_EVENT, array( $this, 'repairFlags' ) );
	}

	public function repairFlags() {
		\SitePress_Setup::fill_flags();
	}

	public function schedule() {
		if ( ! wp_next_scheduled( self::EVENT ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, self::SCHEDULE, self::EVENT );
		}
	}

	public static function unschedule() {
		$timestamp = wp_next_scheduled( self::EVENT );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::EVENT );
		}
	}

	public function run() {
		CatalogueSyncRunner::create()->run();
	}
}
