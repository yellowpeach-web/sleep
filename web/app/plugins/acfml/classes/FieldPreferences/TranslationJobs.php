<?php

namespace ACFML\FieldPreferences;

use ACFML\Field\MetaKeyPatterns;

class TranslationJobs implements \IWPML_Backend_Action, \IWPML_Frontend_Action, \IWPML_DIC_Action {

	const TR_JOB_FIELD_PATTERN = '/^field-(\S+)-[0-9]+$/';

	private $metaKeyPatterns;

	public function __construct( MetaKeyPatterns $metaKeyPatterns ) {
		$this->metaKeyPatterns = $metaKeyPatterns;
	}

	public function add_hooks() {
		add_filter( 'wpml_tm_job_field_is_translatable', [ $this, 'adjust_is_translatable_for_field_in_translation_job' ], 10, 2 );
	}

	public function adjust_is_translatable_for_field_in_translation_job( $is_translatable, $job_translate ) {
		if ( ! $is_translatable && isset( $job_translate['field_type'] ) ) {
			if ( $this->is_acf_field( $job_translate ) ) {
				$is_translatable = true;
			}
		}

		return $is_translatable;
	}

	private function is_acf_field( $job_translate ) {
		if ( ! preg_match( self::TR_JOB_FIELD_PATTERN, $job_translate['field_type'], $matches ) ) {
			return false;
		}

		return $this->metaKeyPatterns->matchesAValueField( $matches[1] );
	}
}
