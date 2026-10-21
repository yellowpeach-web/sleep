<?php

namespace WPML\Upgrade\Commands;

use WPML\LanguageEditor\LanguageCodeResolution;
use WPML\LanguageEditor\RtlLanguages;

class ClearCatalogueDirectionCopies implements \IWPML_Upgrade_Command {

	private $wpdb;

	private $result;

	public function __construct( array $args = [] ) {
		if ( isset( $args[0] ) && $args[0] instanceof \wpdb ) {
			$this->wpdb = $args[0];
		} else {
			global $wpdb;
			$this->wpdb = $wpdb;
		}
	}

	public function run_admin() {
		$this->result = $this->run();

		return $this->result;
	}

	public function run_ajax() {
		return null;
	}

	public function run_frontend() {
		return null;
	}

	public function get_results() {
		return $this->result;
	}

	private function run() {
		$wpdb = $this->wpdb;

		if ( ! SeedLanguageCatalogue::isCataloguePopulated( $wpdb ) ) {
			return false;
		}

		if ( ! $this->hasColumn( 'icl_languages', 'is_rtl' ) || ! $this->hasColumn( 'icl_language_presets', 'rtl' ) ) {
			return false;
		}

		$hasCustom = $this->hasColumn( 'icl_languages', 'is_custom' );
		$rows      = $hasCustom
			? $wpdb->get_results( "SELECT code, is_rtl, is_custom FROM {$wpdb->prefix}icl_languages WHERE is_rtl IS NOT NULL" )
			: $wpdb->get_results( "SELECT code, is_rtl FROM {$wpdb->prefix}icl_languages WHERE is_rtl IS NOT NULL" );
		if ( ! is_array( $rows ) || $wpdb->last_error ) {
			return false;
		}

		LanguageCodeResolution::resetCache();

		$failed  = false;
		$cleared = 0;

		foreach ( $rows as $row ) {
			if ( ! isset( $row->code, $row->is_rtl ) ) {
				continue;
			}
			if ( $hasCustom && ! empty( $row->is_custom ) ) {
				continue;
			}

			$resolved = LanguageCodeResolution::resolve( (string) $row->code );
			if ( null === $resolved || ! isset( $resolved['rtl'] ) ) {
				continue;
			}

			$stored = (int) $row->is_rtl;
			if ( $stored !== (int) $resolved['rtl'] ) {
				continue;
			}

			$updated = $wpdb->update(
				$wpdb->prefix . 'icl_languages',
				[ 'is_rtl' => null ],
				[
					'code'   => (string) $row->code,
					'is_rtl' => $stored,
				],
				[ '%d' ],
				[ '%s', '%d' ]
			);

			if ( false === $updated ) {
				$failed = true;
			} else {
				$cleared += (int) $updated;
			}
		}

		if ( $cleared > 0 ) {
			RtlLanguages::resetStoredOverrides();
		}

		return ! $failed;
	}

	private function hasColumn( $table, $column ) {
		$wpdb = $this->wpdb;

		return (bool) $wpdb->get_var(
			$wpdb->prepare( 'SHOW COLUMNS FROM `' . $wpdb->prefix . esc_sql( $table ) . '` LIKE %s', $column )
		);
	}
}
