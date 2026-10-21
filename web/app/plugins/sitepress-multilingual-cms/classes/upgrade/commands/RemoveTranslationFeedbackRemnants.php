<?php

namespace WPML\Upgrade\Commands;

use WPML\Notices\NoticeStoreRepair;
use WPML\WP\OptionManager;

class RemoveTranslationFeedbackRemnants implements \IWPML_Upgrade_Command {

	const CRON_EVENT = 'wpml_tf_synchronize_ratings_event';

	const OPTIONS = [
		'wpml_tf_settings',
		'wpml_tf_pending_sync_rating_ids',
	];

	const NOTICE_GROUPS = [
		'wpml_tf_backend_notices',
		'wpml-tf-promote',
	];

	private $schema;

	private $options;

	public function __construct( array $args = [] ) {
		$this->schema  = isset( $args[0] ) ? $args[0] : null;
		$this->options = isset( $args[1] ) ? $args[1] : new OptionManager();
	}

	public function run_admin() {
		$timestamp = wp_next_scheduled( self::CRON_EVENT );
		while ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_EVENT );
			$timestamp = wp_next_scheduled( self::CRON_EVENT );
		}

		foreach ( self::OPTIONS as $option ) {
			delete_option( $option );
		}

		$notices = wpml_get_admin_notices();
		foreach ( self::NOTICE_GROUPS as $group ) {
			$notices->remove_notice_group( $group );
		}

		foreach ( $this->notice_store_rows() as $row ) {
			$this->purge_notice_row( $row );
		}

		return true;
	}

	public function run_ajax() {}

	public function run_frontend() {}

	public function get_results() {
		return true;
	}

	private function notice_store_rows() {
		$wpdb = $this->wpdb();

		if ( ! $wpdb ) {
			return [];
		}

		$rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name = %s OR option_name LIKE %s",
				\WPML_Notices::NOTICES_OPTION_KEY,
				$wpdb->esc_like( \WPML_Notices::NOTICES_OPTION_KEY . '_' ) . '%'
			)
		);

		return is_array( $rows ) ? $rows : [];
	}

	private function wpdb() {
		if ( $this->schema ) {
			return $this->schema->get_wpdb();
		}

		return isset( $GLOBALS['wpdb'] ) ? $GLOBALS['wpdb'] : null;
	}

	private function purge_notice_row( $option_name ) {
		$stored = NoticeStoreRepair::readSafely( $option_name );

		if ( ! $this->carries_retired_group( $stored ) ) {
			return;
		}

		$this->options->mutateRaw(
			$option_name,
			function ( $current ) {
				return $this->purge( is_array( $current ) ? $current : [] );
			},
			false
		);
	}

	private function carries_retired_group( array $stored ) {
		foreach ( self::NOTICE_GROUPS as $group ) {
			if ( array_key_exists( $group, $stored ) ) {
				return true;
			}
		}

		return false;
	}

	private function purge( array $stored ) {
		foreach ( self::NOTICE_GROUPS as $group ) {
			unset( $stored[ $group ] );
		}

		return $stored;
	}
}
