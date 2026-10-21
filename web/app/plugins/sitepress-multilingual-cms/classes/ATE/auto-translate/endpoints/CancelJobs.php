<?php

namespace WPML\TM\ATE\AutoTranslate\Endpoint;

require_once __DIR__ . '/../../../../inc/constants-since-5-0.php';

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use WPML\FP\Either;
use WPML\FP\Fns;
use function WPML\Container\make;

class CancelJobs implements IHandler {

	public function run( Collection $data ) {
		if ( $data->get( 'getTotal', false ) ) {
			return Either::of( wpml_tm_get_jobs_repository()->get_count( $this->getSearchParams() ) );
		}

		$batchSize = $data->get( 'batchSize', 1000 );
		$params   = $this->getSearchParams()->set_limit( $batchSize );

		$toCancel = wpml_collect( wpml_tm_get_jobs_repository()->get( $params ) );
		$toCancel->map( Fns::tap( [ make( \WPML_TP_Sync_Update_Job::class ), 'cancel' ] ) );

		return Either::of( $toCancel->count() );
	}

	private function getSearchParams() {
		$searchParams = new \WPML_TM_Jobs_Search_Params();
		$searchParams->set_status( [ ICL_TM_WAITING_FOR_TRANSLATOR, ICL_TM_IN_PROGRESS, ICL_TM_ATE_UNSOLVABLE ] );
		$searchParams->set_custom_where_conditions( [ 'translate_job.automatic = 1' ] );

		return $searchParams;
	}
}
