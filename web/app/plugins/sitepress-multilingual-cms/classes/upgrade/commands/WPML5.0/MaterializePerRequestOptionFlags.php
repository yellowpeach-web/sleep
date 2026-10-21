<?php

namespace WPML\Upgrade\Commands;

use WPML\LanguageEditor\Save\SaveTaskRepository;
use WPML\TM\ATE\Retranslation\Scheduler;

class MaterializePerRequestOptionFlags implements \IWPML_Upgrade_Command {

	const LEGACY_LAST_CALL_OPTION         = 'wpml_ate_retranslation_last_call';
	const LEGACY_UNDELIVERED_CHECK_OPTION = 'wpml_ate_retranslation_last_undelivered_check';

	const GATE_OPTIONS = [
		'wpml_posthog_setup_recording_started_at',
		'wpml_country_migration_ate_mapping_deferred',
	];

	private $results;

	public function run() {
		foreach ( self::GATE_OPTIONS as $option ) {
			$this->materialize_autoloaded( $option, 0 );
		}

		$this->consolidate_retranslation_state();

		( new SaveTaskRepository() )->findActive();

		$this->results = true;
		return $this->results;
	}

	private function materialize_autoloaded( $option, $value ) {
		if ( false !== add_option( $option, $value, '', true ) ) {
			return;
		}

		if ( function_exists( 'wp_set_options_autoload' ) ) {
			wp_set_options_autoload( [ $option ], true );
			return;
		}

		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->options} SET autoload = 'yes'
				 WHERE option_name = %s AND autoload IN ('no','off','auto-off')",
				$option
			)
		);
		wp_cache_delete( $option, 'options' );
		wp_cache_delete( 'alloptions', 'options' );
	}

	private function consolidate_retranslation_state() {
		$state = get_option( Scheduler::STATE_OPTION, [] );
		$state = is_array( $state ) ? $state : [];
		$seed  = $state;

		$last_call = get_option( self::LEGACY_LAST_CALL_OPTION );
		if ( $last_call && ! isset( $seed['last_call'] ) ) {
			$seed['last_call'] = (int) $last_call;
		}

		$last_check = get_option( self::LEGACY_UNDELIVERED_CHECK_OPTION );
		if ( $last_check && ! isset( $seed['last_undelivered_check'] ) ) {
			$seed['last_undelivered_check'] = (int) $last_check;
		}

		if ( $seed !== $state ) {
			update_option( Scheduler::STATE_OPTION, $seed, false );
		}

		delete_option( self::LEGACY_LAST_CALL_OPTION );
		delete_option( self::LEGACY_UNDELIVERED_CHECK_OPTION );
	}

	public function run_admin() {
		return $this->run();
	}

	public function run_ajax() {
		return null;
	}

	public function run_frontend() {
		return null;
	}

	public function get_results() {
		return $this->results;
	}
}
