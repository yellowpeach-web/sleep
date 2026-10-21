<?php

namespace WPML\TM\Settings\Flags;

use WPML\FP\Obj;

class FlagsRepository {
	private $wpdb;

	public function __construct( \wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function getItems( $data = array() ) {
		$wpdb = $this->wpdb;
		if ( Obj::has( 'onlyInstalledByDefault', $data ) ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT
						flags.id,
						flags.lang_code,
						flags.flag,
						flags.from_template
					FROM {$wpdb->prefix}icl_flags flags
					WHERE flags.from_template = %d",
					0
				)
			);
		}

		return $wpdb->get_results(
			"SELECT
					flags.id,
					flags.lang_code,
					flags.flag,
					flags.from_template
				FROM {$wpdb->prefix}icl_flags flags"
		);
	}

	public function getItemsInstalledByDefault( $data = array() ) {
		return $this->getItems( array_merge(
			$data,
			[
				'onlyInstalledByDefault' => true,
			]
		) );
	}

	private function getFlagsCountByExt( $ext ) {
		$wpdb = $this->wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(flags.id)
				FROM {$wpdb->prefix}icl_flags flags
				WHERE flags.from_template = 0 AND flags.flag LIKE %s",
				'%.' . $ext
			)
		);
	}

	public function hasSvgFlags() {
		return $this->getFlagsCountByExt( 'svg' ) > 0;
	}

	public function hasPngFlags() {
		return $this->getFlagsCountByExt( 'png' ) > 0;
	}
}
