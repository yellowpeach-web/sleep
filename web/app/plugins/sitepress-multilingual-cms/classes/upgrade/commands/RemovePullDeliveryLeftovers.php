<?php

namespace WPML\TM\Upgrade\Commands;

use WPML\TM\ATE\PullDelivery\State;

class RemovePullDeliveryLeftovers extends \WPML_Upgrade_Run_All {

	const CRON_HOOK = 'wpml_ate_pull_delivery_tick';

	const OPTIONS = [ 'wpml_pull_delivery_apply_backoff', 'wpml_pull_delivery_ready_queue' ];

	const COLLECT_TOKEN_TRANSIENT = 'wpml_pull_delivery_collect_token';

	const REPORT_TRANSIENT_PREFIX = 'wpml_ate_fetch_refused_report_';

	const PACER_LOCK_OPTIONS = [
		'wpml.WPML\TM\ATE\PullDelivery\Pacer.lock',
		'wpml.WPML\TM\ATE\PullDelivery\Pacer.lock.key',
	];

	private $wpdb;

	public function __construct( array $args ) {
		$this->wpdb = $args[0];
	}

	protected function run() {
		wp_clear_scheduled_hook( self::CRON_HOOK );

		foreach ( array_merge( self::OPTIONS, self::PACER_LOCK_OPTIONS ) as $option ) {
			delete_option( $option );
		}

		delete_transient( self::COLLECT_TOKEN_TRANSIENT );

		$wpdb = $this->wpdb;

		foreach ( [ '_transient_', '_transient_timeout_' ] as $kind ) {
			$like = $wpdb->esc_like( $kind . self::REPORT_TRANSIENT_PREFIX ) . '%';
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) );
		}

		$stored = get_option( State::OPTION, null );

		if ( is_array( $stored ) && array_diff_key( $stored, State::defaults() ) ) {
			State::update( [], array_intersect_key( $stored, State::defaults() ) );
		}

		return true;
	}
}
