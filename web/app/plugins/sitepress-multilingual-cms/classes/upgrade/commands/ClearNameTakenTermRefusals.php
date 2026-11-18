<?php

namespace WPML\Upgrade\Commands;

use WPML\TM\ATE\TranslateEverything\RefusedTermName;

class ClearNameTakenTermRefusals implements \IWPML_Upgrade_Command {

	const REASON_START = 'The translated term name is already used by another term in this language';

	private $wpdb;

	private $deleted = 0;

	public function __construct( array $args ) {
		$this->wpdb = $args[0];
	}

	public function run_admin() {
		$wpdb = $this->wpdb;

		$reasonPrefix = RefusedTermName::REASON_META_PREFIX;
		$namePrefix   = RefusedTermName::NAME_MD5_META_PREFIX;

		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE reason_row, name_row
				FROM {$wpdb->termmeta} reason_row
				LEFT JOIN {$wpdb->termmeta} name_row
					ON name_row.term_id = reason_row.term_id
					AND name_row.meta_key = CONCAT( %s, SUBSTRING( reason_row.meta_key, %d ) )
				WHERE reason_row.meta_key LIKE %s
					AND reason_row.meta_value LIKE %s",
				$namePrefix,
				strlen( $reasonPrefix ) + 1,
				$wpdb->esc_like( $reasonPrefix ) . '%',
				$wpdb->esc_like( self::REASON_START ) . '%'
			)
		);

		if ( false === $deleted ) {
			return false;
		}

		$this->deleted = (int) $deleted;
		if ( $this->deleted > 0 && function_exists( 'wp_cache_supports' ) && wp_cache_supports( 'flush_group' ) ) {
			wp_cache_flush_group( 'term_meta' );
		}

		return true;
	}

	public function run_ajax() {}

	public function run_frontend() {
		return $this->run_admin();
	}

	public function get_results() {
		return $this->deleted;
	}
}
