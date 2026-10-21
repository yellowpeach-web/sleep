<?php

namespace WPML\Upgrade\Commands;

class NormalizeNullDefaultLocale implements \IWPML_Upgrade_Command {

	const COLD_CACHE_OPTION = '_icl_cache_language_details';

	private $wpdb;

	private $result;

	public function __construct( array $args = array() ) {
		if ( isset( $args[0] ) && $args[0] instanceof \wpdb ) {
			$this->wpdb = $args[0];
		} else {
			global $wpdb;
			$this->wpdb = $wpdb;
		}
	}

	private function run() {
		$wpdb = $this->wpdb;

		$null_rows = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}icl_languages WHERE default_locale IS NULL" );

		if ( 0 === $null_rows ) {
			$this->result = true;

			return true;
		}

		if ( false === \wpml_language_cache_rotate_epoch() ) {
			$this->result = false;

			return false;
		}

		$updated = $wpdb->update(
			$wpdb->prefix . 'icl_languages',
			array( 'default_locale' => '' ),
			array( 'default_locale' => null )
		);

		if ( false === $updated ) {
			$this->result = false;

			return false;
		}

		\icl_cache_clear( false );
		delete_option( self::COLD_CACHE_OPTION );
		\wpml_language_cache_delete_shards();

		$this->result = true;

		return true;
	}

	public function run_admin() {
		return $this->run();
	}

	public function run_ajax() {
		return $this->run();
	}

	public function run_frontend() {
		return $this->run();
	}

	public function get_results() {
		return $this->result;
	}
}
