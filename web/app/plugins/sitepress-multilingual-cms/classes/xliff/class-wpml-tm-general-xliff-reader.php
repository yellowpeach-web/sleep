<?php

class WPML_TM_General_Xliff_Reader extends WPML_TM_Xliff_Reader {

	public function get_xliff_job_identifier( $content ) {
		$xliff = $this->load_xliff( $content );
		if ( is_wp_error( $xliff ) ) {
			$identifier = false;
		} else {
			$identifier = $this->identifier_from_xliff( $xliff );
		}

		return $identifier;
	}

	public function get_data( $content ) {
		$xliff = $this->load_xliff( $content );
		if ( is_wp_error( $xliff ) ) {
			$data = $xliff;
		} else {
			$job  = $this->get_job_for_xliff( $xliff );
			$data = is_wp_error( $job ) ? $job : $this->generate_job_data( $xliff, $job );
		}

		return $data;
	}

	public function get_data_for_job( $content, $job_id ) {
		$xliff = $this->load_xliff( $content );
		if ( is_wp_error( $xliff ) ) {
			return $xliff;
		}

		$job = $this->job_factory->get_translation_job( (int) $job_id, false, 1, false );
		if ( ! $job ) {
			return $this->does_not_belong_error( $this->mismatch_reason( (int) $job_id, (int) $job_id, false, false ) );
		}

		return $this->generate_job_data( $xliff, $job );
	}

	public function cleaned_job_declared_for_original_of( $content, $job_id ) {
		$xliff = $this->load_xliff( $content );
		if ( is_wp_error( $xliff ) ) {
			return 0;
		}

		$parts = explode( '-', $this->identifier_from_xliff( $xliff ) );
		if ( count( $parts ) !== 2 || ! is_numeric( $parts[0] ) ) {
			return 0;
		}

		$declared = (int) apply_filters( 'wpml_job_id', $parts[0] );
		if ( $declared <= 0 || $this->job_factory->get_translation_job( $declared, false, 1, false ) ) {
			return 0;
		}

		$job = $this->job_factory->get_translation_job( (int) $job_id, false, 1, false );
		if ( ! $job ) {
			return 0;
		}

		return hash_equals( self::job_identifier_md5( $declared, $job->original_doc_id ), (string) $parts[1] ) ? $declared : 0;
	}
}
