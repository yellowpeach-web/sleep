<?php

namespace WPML\TM\ATE\Retranslation;

use WPML\TM\Jobs\JobLog;

class SinglePageBatchHandler {

	const NOT_FINISHED_IN_ATE = 'retranslations-no-finished-in-ate';
	const FINISHED_IN_WPML = 'retranslations-finished-in-wpml';
	const GO_TO_NEXT_PAGE = 'retranslate-next-page';


	private $jobsCollector;

	private $retranslationPreparer;

	private $taskManager;

	public function __construct( JobsCollector $jobsCollector, RetranslationPreparer $retranslationPreparer, BackgroundTask\TaskManager $taskManager ) {
		$this->jobsCollector         = $jobsCollector;
		$this->retranslationPreparer = $retranslationPreparer;
		$this->taskManager           = $taskManager;
	}

	public function handle( int $pageNumber = 1 ): array {
		$jobsBatch = $this->jobsCollector->get( $pageNumber );
		$ateJobIds = $jobsBatch->getJobIds();

		if ( ! $jobsBatch->isRetranslationFinished() ) {
			JobLog::addRetranslationEvent( 'retranslation_sync_page_decision', [
				'page'          => $pageNumber,
				'state'         => self::NOT_FINISHED_IN_ATE,
				'total_pages'   => $jobsBatch->getTotalPages(),
				'ate_job_count' => count( $ateJobIds ),
				'next_page'     => 0,
			] );

			return [ 'state' => self::NOT_FINISHED_IN_ATE, 'nextPage' => 0 ];
		}

		$freshWpmlJobIds = [];
		if ( $ateJobIds ) {
			list( , , $freshWpmlJobIds ) = $this->retranslationPreparer->delegate( $ateJobIds );
			$this->taskManager->addJobsToSyncBatch( $freshWpmlJobIds );
		}

		$result = $pageNumber >= $jobsBatch->getTotalPages()
			? [ 'state' => self::FINISHED_IN_WPML, 'nextPage' => 0 ]
			: [ 'state' => self::GO_TO_NEXT_PAGE, 'nextPage' => $pageNumber + 1 ];

		JobLog::addRetranslationEvent( 'retranslation_sync_page_decision', [
			'page'                 => $pageNumber,
			'state'                => $result['state'],
			'total_pages'          => $jobsBatch->getTotalPages(),
			'ate_job_count'        => count( $ateJobIds ),
			'fresh_wpml_job_count' => count( $freshWpmlJobIds ),
			'next_page'            => $result['nextPage'],
		] );

		return $result;
	}

}
