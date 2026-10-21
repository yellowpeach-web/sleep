<?php

namespace WPML\Upgrade\Commands;

class RemoveLegacyWordCountState implements \IWPML_Upgrade_Command {

	const STATUS_OPTION = 'wpml_word_count_requested_types_status';

	const PROCESS_IDENTIFIER = 'wpml_tm_word_count_background_process_requested_types';

	private $wpdb;

	public function __construct( array $args ) {
		$this->wpdb = $args[0];
	}

	public function run_admin() {
		$cron_hook = self::PROCESS_IDENTIFIER . '_cron';
		$timestamp = wp_next_scheduled( $cron_hook );
		while ( $timestamp ) {
			wp_unschedule_event( $timestamp, $cron_hook );
			$timestamp = wp_next_scheduled( $cron_hook );
		}

		delete_option( self::STATUS_OPTION );
		delete_site_transient( self::PROCESS_IDENTIFIER . '_process_lock' );

		$wpdb  = $this->wpdb;
		$like  = $wpdb->esc_like( self::PROCESS_IDENTIFIER . '_batch_' ) . '%';
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) );

		return true;
	}

	public function run_ajax() {}

	public function run_frontend() {}

	public function get_results() {
		return true;
	}
}
