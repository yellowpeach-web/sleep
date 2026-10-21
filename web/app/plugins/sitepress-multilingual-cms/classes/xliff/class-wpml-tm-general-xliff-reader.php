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
			return $this->does_not_belong_error();
		}

		return $this->generate_job_data( $xliff, $job );
	}
}
