<?php

namespace WPML\TM\ATE\Download;

use Exception;
use WPML\FP\Obj;
use WPML\TM\ATE\ReturnedJobs;
use WPML\TM\ATE\TranslateEverything\TaxonomyTermJobApplier;
use WPML_TM_ATE_API;
use WPML_TM_ATE_Jobs;

class Consumer {

	private $ateApi;

	private $ateJobs;

	public function __construct( WPML_TM_ATE_API $ateApi, WPML_TM_ATE_Jobs $ateJobs ) {
		$this->ateApi  = $ateApi;
		$this->ateJobs = $ateJobs;
	}

	public function process( $job ) {
		$xliffContent = $this->ateApi->get_remote_xliff_content( Obj::prop( 'url', $job ), $job );

		$expectedJobId = (int) Obj::prop( 'jobId', $job );
		$wpmlJobId     = $this->ateJobs->apply( $xliffContent, $expectedJobId ?: null, (int) Obj::prop( 'ateJobId', $job ) ?: null );

		if ( $wpmlJobId ) {
			$job = Obj::assoc( 'jobId', $wpmlJobId, $job );
			$job = Obj::assoc( 'status', TaxonomyTermJobApplier::lastDeliveryGivenUp() ? ICL_TM_ATE_UNSOLVABLE : ICL_TM_COMPLETE, $job );

			return $job;
		}

		return false;
	}
}
