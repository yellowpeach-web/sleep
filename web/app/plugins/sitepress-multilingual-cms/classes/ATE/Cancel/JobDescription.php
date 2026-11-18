<?php

namespace WPML\TM\ATE\Cancel;

class JobDescription {

	public static function of( $wpmlJobId ) {
		$description = [ 'title' => '', 'language' => '' ];
		$wpmlJobId   = (int) $wpmlJobId;

		if ( $wpmlJobId <= 0 ) {
			return $description;
		}

		try {
			$job = wpml_tm_load_job_factory()->get_translation_job( $wpmlJobId, false, 0, true );
		} catch ( \Throwable $e ) {
			return $description;
		}

		if ( ! is_object( $job ) || ! method_exists( $job, 'get_title' ) ) {
			return $description;
		}

		$title    = $job->get_title();
		$language = method_exists( $job, 'get_language_code' ) ? $job->get_language_code( true ) : '';

		return [
			'title'    => is_string( $title ) ? $title : '',
			'language' => is_string( $language ) ? $language : '',
		];
	}
}
