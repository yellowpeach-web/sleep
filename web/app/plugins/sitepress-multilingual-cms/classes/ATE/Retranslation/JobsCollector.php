<?php

namespace WPML\TM\ATE\Retranslation;

use WPML\FP\Obj;
use WPML\TM\ATE\Retranslation\JobsCollector\ATEResponse;
use WPML\TM\Jobs\JobLog;

class JobsCollector {

	private $ateAPI;

	public function __construct( \WPML_TM_ATE_API $ateAPI ) {
		$this->ateAPI = $ateAPI;
	}


	public function get( int $page = 1 ): ATEResponse {
		$result = $this->ateAPI->get_jobs_to_retranslation( $page );

		$response = $result->map( function ( $result ) {
			return new ATEResponse(
				Obj::propOr( false, 'retranslation_finished', $result),
				Obj::propOr( [], 'job_ids', $result ),
				Obj::propOr( 0, 'page', $result ),
				Obj::propOr( 0, 'pages', $result )
			);
		} )->getOrElse( null );

		if ( ! $response ) {
			$response = new ATEResponse( false, [], 0, 0 );
		}

		JobLog::addRetranslationEvent( 'retranslation_sync_ate_response', [
			'requested_page' => $page,
			'response_page'  => $response->getCurrentPage(),
			'finished'       => $response->isRetranslationFinished(),
			'total_pages'    => $response->getTotalPages(),
			'ate_job_count'  => count( $response->getJobIds() ),
			'response_ok'    => null !== $result->getOrElse( null ),
		] );

		return $response;
	}

}
