<?php

namespace WPML\TM\XLIFF;

use WPML\Translation\TranslationElements\FieldCompression;

class SourceJob {

	const MAX_JOBS = 25;

	private $wpdb;

	private $job_id;

	public function __construct( $wpdb, $job_id ) {
		$this->wpdb   = $wpdb;
		$this->job_id = (int) $job_id;
	}

	public function find( $field_type, $translation ) {
		$translation = trim( (string) $translation );
		if ( '' === $translation ) {
			return null;
		}

		$wpdb = $this->wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT j.job_id, j.editor, t.field_data, t.field_data_translated
				FROM {$wpdb->prefix}icl_translate_job j
				INNER JOIN {$wpdb->prefix}icl_translate t ON t.job_id = j.job_id AND t.field_type = %s
				WHERE j.rid = ( SELECT rid FROM {$wpdb->prefix}icl_translate_job WHERE job_id = %d )
				AND j.job_id < %d
				AND ( j.completed_date IS NOT NULL OR ( j.editor = 'wp' AND j.translated = 1 ) )
				AND j.editor IN ( 'ate', 'wpml', 'wp' )
				ORDER BY j.job_id DESC
				LIMIT %d",
				(string) $field_type,
				$this->job_id,
				$this->job_id,
				self::MAX_JOBS
			)
		);

		foreach ( (array) $rows as $row ) {
			if ( trim( (string) FieldCompression::decompress( $row->field_data_translated ) ) !== $translation ) {
				continue;
			}

			return array(
				'source'      => (string) FieldCompression::decompress( $row->field_data ),
				'made_in_ate' => \WPML_TM_Editors::ATE === $row->editor,
			);
		}

		return null;
	}
}
