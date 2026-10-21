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
