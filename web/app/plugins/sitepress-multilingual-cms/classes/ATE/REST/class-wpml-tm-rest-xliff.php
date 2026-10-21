<?php

class WPML_TM_REST_XLIFF extends WPML_TM_ATE_Required_Rest_Base {
	const CAPABILITY = 'translate';

	function add_hooks() {
		$this->register_routes();
	}

	function register_routes() {
		parent::register_route(
			'/xliff/fetch/(?P<jobId>\d+)',
			array(
				'methods'  => 'GET',
				'callback' => array( $this, 'fetch_xliff' ),
			)
		);
	}

	public function fetch_xliff( WP_REST_Request $request ) {
		$wpml_translation_job_factory = wpml_tm_load_job_factory();

		$job_id = (int) $request->get_param( 'jobId' );

		$job = $wpml_translation_job_factory->get_translation_job( $job_id, false, 1, true );

		if ( ! $job ) {
			return new WP_Error(
				'wpml_job_not_found',
				sprintf( 'Translation job %d was not found.', $job_id ),
				array( 'status' => 404 )
			);
		}

		$authorized = \WPML\TM\Jobs\Authorization\AuthorizedJobResolver::make()->byLocalId(
			\WPML\Core\Security\ExecutionContext\ExecutionContextHolder::current(),
			$job_id
		);
		if ( ! $authorized ) {
			return new WP_Error(
				'wpml_tm_xliff_forbidden',
				__( 'You are not allowed to access this translation job.', 'sitepress' ),
				array( 'status' => 403 )
			);
		}

		$writer = ( new WPML_TM_Xliff_Writer( $wpml_translation_job_factory ) )->withDeclaredPlaceholders();
		$xliff  = $writer->generate_job_xliff_or_null( $job_id );

		if ( null === $xliff ) {
			return new WP_Error(
				'wpml_xliff_no_translation_units',
				sprintf( 'Translation job %d has no translatable content.', $job_id ),
				array( 'status' => 422 )
			);
		}

		return array(
			'content'    => base64_encode( $xliff ),
			'sourceLang' => $job->get_source_language_code(),
			'targetLang' => $job->get_language_code(),
		);
	}

	function get_allowed_capabilities( WP_REST_Request $request ) {
		return self::CAPABILITY;
	}
}
