<?php

namespace WPML\TM\Jobs;

class PreviousAteJobIds {

	const LIMIT = 15;

	private $wpdb;

	public function __construct( $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function of( int $rid ): array {
		$wpdb = $this->wpdb;

		return array_map(
			'intval',
			$wpdb->get_col(
				$wpdb->prepare(
					"SELECT editor_job_id
					FROM {$wpdb->prefix}icl_translate_job j
					WHERE rid = %d
					AND j.completed_date IS NOT NULL
					AND j.editor_job_id > 0
					ORDER BY j.completed_date DESC
					LIMIT %d",
					$rid,
					self::LIMIT
				)
			)
		);
	}
}
