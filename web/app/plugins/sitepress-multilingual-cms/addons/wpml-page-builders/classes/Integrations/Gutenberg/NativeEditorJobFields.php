<?php

namespace WPML\PB\Gutenberg;

use WPML\Translation\TranslationElements\FieldCompression;

class NativeEditorJobFields {

	private $sitepress;

	private $load_job;

	public function __construct( \SitePress $sitepress, ?callable $load_job = null ) {
		$this->sitepress = $sitepress;
		$this->load_job  = $load_job ?: function ( $job_id ) {
			return new \WPML_Post_Translation_Job( $job_id );
		};
	}

	public function refresh( $translated_post_id, $element_type, $language, array $values ) {
		if ( ! $values ) {
			return 0;
		}

		$trid   = $this->sitepress->get_element_trid( (int) $translated_post_id, $element_type );
		$job_id = $trid ? (int) wpml_load_core_tm()->get_translation_job_id( $trid, $language ) : 0;

		if ( ! $job_id ) {
			return 0;
		}

		$job = call_user_func( $this->load_job, $job_id );

		foreach ( $values as $field_type => $value ) {
			$job->set_translated_field_by_type( $field_type, $this->encode( $value, $job_id ) );
		}

		return count( $values );
	}

	protected function encode( $value, $job_id ) {
		return FieldCompression::compressAndTrack( base64_encode( $value ), true, $job_id );
	}
}
