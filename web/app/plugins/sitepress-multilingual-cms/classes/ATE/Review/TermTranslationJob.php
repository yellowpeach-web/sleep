<?php

namespace WPML\TM\ATE\Review;

use WPML\FP\Obj;

class TermTranslationJob extends \WPML_Translation_Job {

	public static function load( $jobId ) {
		$row = TermJob::get( (int) $jobId );

		return $row ? new self( (int) $jobId, $row ) : false;
	}

	public function __construct( $jobId, $row, $blogTranslators = null ) {
		$this->job_id           = (int) $jobId;
		$this->basic_data       = $row;
		$this->blog_translators = $blogTranslators ? $blogTranslators : wpml_tm_load_blog_translators();
	}

	public function get_type() {
		return \WPML_TM_Job_Entity::TAXONOMY_TYPE;
	}

	public function get_original_element_id() {
		global $wpdb;

		$this->maybe_load_basic_data();

		$elementId = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE trid = %d AND language_code = %s LIMIT 1",
				(int) Obj::prop( 'trid', $this->basic_data ),
				(string) Obj::prop( 'source_language_code', $this->basic_data )
			)
		);

		return null === $elementId ? null : (int) $elementId;
	}

	public function get_title() {
		$this->maybe_load_basic_data();

		$elementType = (string) Obj::prop( 'element_type', $this->basic_data );
		$prefix      = TermJob::ELEMENT_TYPE_PREFIX . '_';
		if ( 0 !== strpos( $elementType, $prefix ) ) {
			return '';
		}

		$elementId = $this->get_original_element_id();
		$term      = $elementId ? get_term_by( 'term_taxonomy_id', $elementId, substr( $elementType, strlen( $prefix ) ) ) : false;

		return $term && isset( $term->name ) ? esc_html( $term->name ) : '';
	}

	public function to_array() {
		$data           = $this->basic_data_to_array( $this->get_basic_data() );
		$data['job_id'] = $this->get_id();
		$data['title']  = $this->get_title();
		$data['status'] = $this->get_status();

		return $data;
	}

	protected function load_job_data( $id ) {
		return TermJob::get( (int) $id );
	}

	protected function load_status() {
		$this->maybe_load_basic_data();

		$status = ! empty( $this->basic_data->translated ) ? ICL_TM_COMPLETE : Obj::prop( 'status', $this->basic_data );

		return \TranslationManagement::get_job_status_string( $status );
	}

	protected function load_resultant_element_id() {
		$this->maybe_load_basic_data();

		$elementId = Obj::prop( 'element_id', $this->basic_data );

		return null === $elementId ? null : (int) $elementId;
	}

	protected function save_updated_assignment() {
		return TermJob::assign( (int) $this->job_id, $this->get_translator_id() );
	}

	protected function load_batch_id() {
		global $wpdb;

		$this->maybe_load_basic_data();

		$this->batch_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT batch_id FROM {$wpdb->prefix}icl_translation_status WHERE rid = %d LIMIT 1",
				(int) Obj::prop( 'rid', $this->basic_data )
			)
		);
	}
}
