<?php

namespace WPML\TM\Jobs;

use WPML_External_Translation_Job;

require_once WPML_TM_PATH . '/inc/translation-jobs/jobs/wpml-external-translation-job.class.php';

class StringBatchTranslationJob extends WPML_External_Translation_Job {

	private $title;

	public function set_title( $title ) {
		$this->title = (string) $title;
	}

	public function get_title() {
		if ( null === $this->title ) {
			global $wpdb;

			$batch_id    = (int) $this->get_original_element_id();
			$titles      = ( new StringBatchTitles( $wpdb ) )->get_titles( array( $batch_id ) );
			$this->title = isset( $titles[ $batch_id ] ) ? $titles[ $batch_id ] : '';
		}

		return $this->title;
	}

	public function get_type_title() {
		return $this->get_title();
	}

	public function get_url( $original = false ) {
		return '';
	}

	public function get_original_document() {
		return null;
	}
}
