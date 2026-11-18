<?php

use WPML\Translation\TranslationElements\FieldCompression;

class WPML_TM_Job_Elements_Repository {

	private $wpdb;

	public function __construct( wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function get_job_elements( WPML_TM_Post_Job_Entity $job ) {
		$wpdb = $this->wpdb;

		$rowset = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT translate.*
				FROM {$wpdb->prefix}icl_translate translate
				WHERE job_id = %d",
				$job->get_translate_job_id()
			)
		);

		return is_array( $rowset )
			? array_map( array( $this, 'build_element_entity' ), $rowset )
			: [];
	}

	public function get_latest_translated_job_body( $translated_post_id ) {
		$wpdb = $this->wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT job.job_id, body.tid, body.field_translate, body.field_finished, body.field_data_translated
				FROM {$wpdb->prefix}icl_translations translations
				INNER JOIN {$wpdb->prefix}icl_translation_status translation_status
					ON translation_status.translation_id = translations.translation_id
				INNER JOIN {$wpdb->prefix}icl_translate_job job
					ON job.job_id = (
						SELECT MAX(latest.job_id)
						FROM {$wpdb->prefix}icl_translate_job latest
						WHERE latest.rid = translation_status.rid AND latest.translated = 1
					)
				LEFT JOIN {$wpdb->prefix}icl_translate body
					ON body.job_id = job.job_id AND body.field_type = 'body'
				WHERE translations.element_id = %d
					AND translations.element_type LIKE %s
				LIMIT 1",
				(int) $translated_post_id,
				$wpdb->esc_like( 'post_' ) . '%'
			)
		);

		if ( ! is_object( $row ) || ! isset( $row->job_id ) ) {
			return null;
		}

		$has_body = isset( $row->tid ) && null !== $row->tid;
		$data     = $has_body ? FieldCompression::decompress( (string) $row->field_data_translated ) : '';

		return [
			'job_id'          => (int) $row->job_id,
			'has_body'        => $has_body,
			'field_translate' => $has_body ? (int) $row->field_translate : 0,
			'field_finished'  => $has_body ? (int) $row->field_finished : 0,
			'data'            => is_string( $data ) ? $data : '',
		];
	}

	private function build_element_entity( stdClass $raw_data ) {
		return new WPML_TM_Job_Element_Entity(
			$raw_data->tid,
			$raw_data->content_id,
			$raw_data->timestamp,
			$raw_data->field_type,
			$raw_data->field_format,
			$raw_data->field_translate,
			FieldCompression::decompress( $raw_data->field_data, true ),
			FieldCompression::decompress( $raw_data->field_data_translated, true ),
			$raw_data->field_finished
		);
	}
}
