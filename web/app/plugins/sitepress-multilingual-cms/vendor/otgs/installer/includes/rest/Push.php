<?php

namespace OTGS\Installer\Rest;

use \WP_REST_Response;

class Push {

	const REFRESH_INTERVAL = 7200;

	const REST_NAMESPACE = 'otgs/installer/v1';

	const LOCK_OPTION  = 'otgs_installer_push_refresh_lock';
	const LOCK_SECONDS = 60;

	public static function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'push/fetch-subscription',
			[
				'methods'  => 'GET',
				'callback' => self::class . '::fetch_subscription',
				'permission_callback' => '__return_true',
			]
		);
	}

	public static function fetch_subscription() {
		$installer    = OTGS_Installer();
		$last_refresh = $installer->get_last_subscriptions_refresh();

		if ( defined( 'OTGS_INSTALLER_OVERRIDE_SUB_LAST_REFRESH' ) ) {
			$last_refresh = constant( 'OTGS_INSTALLER_OVERRIDE_SUB_LAST_REFRESH' );
		}

		if ( time() - $last_refresh > self::REFRESH_INTERVAL ) {
			if ( ! self::lock() ) {
				return new WP_REST_Response( [ 'message' => 'A refresh is running' ], 409 );
			}

			try {
				$installer->refresh_subscriptions_data();
			} finally {
				self::unlock();
			}

			do_action( 'otgs_installer_subscription_refreshed' );

			return new WP_REST_Response( [ 'message' => 'OK' ], 200 );
		}

		return new WP_REST_Response( [ 'message' => 'Not stale' ], 200 );
	}

	private static function lock() {
		global $wpdb;

		$now = time();
		if ( $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO `{$wpdb->options}` ( `option_name`, `option_value`, `autoload` ) VALUES ( %s, %s, 'no' ) /* LOCK */", self::LOCK_OPTION, $now ) ) ) {
			return true;
		}

		$taken_at = $wpdb->get_var( $wpdb->prepare( "SELECT `option_value` FROM `{$wpdb->options}` WHERE `option_name` = %s", self::LOCK_OPTION ) );
		if ( null === $taken_at || (int) $taken_at > $now - self::LOCK_SECONDS ) {
			return false;
		}

		return (bool) $wpdb->query( $wpdb->prepare( "UPDATE `{$wpdb->options}` SET `option_value` = %s WHERE `option_name` = %s AND `option_value` = %s", $now, self::LOCK_OPTION, $taken_at ) );
	}

	private static function unlock() {
		global $wpdb;

		$wpdb->query( $wpdb->prepare( "DELETE FROM `{$wpdb->options}` WHERE `option_name` = %s", self::LOCK_OPTION ) );
	}
}
