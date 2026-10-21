<?php

class WPML_TM_Rest_Job_Progress {
	private $wpdb;

	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
	}

	public function get( WPML_TM_Job_Entity $job ) {
		if ( $job->get_translation_service() !== 'local' ) {
			return '';
		}

		if ( $job->get_status() !== ICL_TM_IN_PROGRESS ) {
			return '';
		}

		if ( in_array( $job->get_type(), [ WPML_TM_Job_Entity::STRING_TYPE, WPML_TM_Job_Entity::TAXONOMY_TYPE ], true ) ) {
			return '';
		}

		$wpdb       = $this->wpdb;
		$elements   = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT field_finished FROM {$wpdb->prefix}icl_translate translate
				INNER JOIN {$wpdb->prefix}icl_translate_job translate_job ON translate_job.job_id = translate.job_id
				INNER JOIN {$wpdb->prefix}icl_translation_status translation_status ON translation_status.rid = translate_job.rid
				WHERE translation_status.rid = %d AND translate.field_translate = 1 AND LENGTH(translate.field_data) > 0",
				$job->get_id()
			)
		);
		$translated = array_filter( $elements );

		if ( ! $elements ) {
			return '';
		}

		$percentage = (int) ( count( $translated ) / count( $elements ) * 100 );

		/* translators: How far a translation job has got, shown in the table of translation jobs. %s: a percentage, already written with its per cent sign, for example "40%". */
		return sprintf( _x( '%s completed', 'Translation jobs list', 'sitepress' ), $percentage . '%' );
	}
}
