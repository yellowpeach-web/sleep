<?php

namespace WPML\TM\ATE\Retranslation\BackgroundTask;

use WPML\FP\Obj;
use WPML\TM\Jobs\JobLog;

class ProgressHook implements \IWPML_Backend_Action, \IWPML_REST_Action, \IWPML_DIC_Action {

	private $taskManager;

	public function __construct( TaskManager $taskManager ) {
		$this->taskManager = $taskManager;
	}

	public function add_hooks() {
		add_action( 'wpml_tm_ate_jobs_downloaded', [ $this, 'recordDownloadedJobs' ], 10, 1 );
	}

	public function recordDownloadedJobs( $jobs ) {
		$jobIds = \wpml_collect( $jobs )
			->map( Obj::prop( 'jobId' ) )
			->filter()
			->map( function ( $jobId ) {
				return (int) $jobId;
			} )
			->values()
			->toArray();

		JobLog::addRetranslationEvent( 'retranslation_downloaded_jobs_seen', [
			'downloaded_count' => count( $jobIds ),
			'job_id_sample'    => array_slice( $jobIds, 0, 10 ),
		] );

		if ( $jobIds ) {
			$this->taskManager->recordJobsApplied( $jobIds );
		}
	}
}
