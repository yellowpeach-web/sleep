<?php

namespace WPML\Upgrade\Commands;

class RepairZeroDisplayCode implements \IWPML_Upgrade_Command {

	const TABLE  = 'icl_languages';
	const COLUMN = 'display_code';

	private $schema;

	private $sitepress;

	private $result;

	public function __construct( array $args ) {
		$this->schema    = $args[0];
		$this->sitepress = $args[1];
	}

	public function run() {
		if ( \WPML_Settings_Failsafe_Loader::isUnrecoverable() ) {
			return false;
		}

		if ( ! $this->schema->does_column_exist( self::TABLE, self::COLUMN ) ) {
			return false;
		}

		$wpdb = $this->schema->get_wpdb();

		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}icl_languages SET display_code = CASE WHEN active = 1 THEN code ELSE NULL END WHERE display_code = %s",
				'0'
			)
		);

		$this->forgetZeroDomains();

		if ( $updated ) {
			do_action( 'wpml_update_active_languages' );
		}

		$this->result = true;

		return true;
	}

	private function forgetZeroDomains() {
		$domains = $this->sitepress->get_setting( 'language_domains', [] );
		if ( ! is_array( $domains ) || ! $domains ) {
			return;
		}

		$zeroHost = strtolower( '0.' . \WPML_Lang_Domains_Box::suggested_base_host( (string) get_option( 'home' ) ) );
		$kept     = [];
		foreach ( $domains as $code => $domain ) {
			if ( is_string( $domain ) && self::hostOf( $domain ) === $zeroHost ) {
				continue;
			}
			$kept[ $code ] = $domain;
		}

		if ( count( $kept ) !== count( $domains ) ) {
			$this->sitepress->set_setting( 'language_domains', $kept, true );
		}
	}

	private static function hostOf( $domain ) {
		$value = (string) preg_replace( '#^[a-z][a-z0-9+.-]*://#i', '', trim( $domain ) );
		$slash = strpos( $value, '/' );

		return strtolower( false === $slash ? $value : substr( $value, 0, $slash ) );
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
