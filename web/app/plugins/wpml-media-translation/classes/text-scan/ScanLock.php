<?php

namespace WPML\Media\TextScan;

class ScanLock {

	const OPTION      = '_wpml_media_text_scan_lock';
	const STALE_AFTER = 60;

	private $wpdb;

	private $clock;

	private $held;

	public function __construct( \wpdb $wpdb, $clock = null ) {
		$this->wpdb  = $wpdb;
		$this->clock = $clock ?: function () {
			return microtime( true );
		};
	}

	public function acquire() {
		$now  = (int) floor( call_user_func( $this->clock ) );
		$wpdb = $this->wpdb;

		$inserted = $wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
				self::OPTION,
				(string) $now
			)
		);
		if ( 1 === (int) $inserted ) {
			$this->held = $now;

			return true;
		}

		$old = $wpdb->get_var(
			$wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::OPTION )
		);
		if ( null === $old || $now - (int) $old <= self::STALE_AFTER ) {
			return false;
		}

		$stolen = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s",
				(string) $now,
				self::OPTION,
				(string) $old
			)
		);
		if ( 1 === (int) $stolen ) {
			$this->held = $now;

			return true;
		}

		return false;
	}

	public function release() {
		if ( null === $this->held ) {
			return;
		}

		$wpdb = $this->wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s",
				self::OPTION,
				(string) $this->held
			)
		);
		$this->held = null;
	}
}
