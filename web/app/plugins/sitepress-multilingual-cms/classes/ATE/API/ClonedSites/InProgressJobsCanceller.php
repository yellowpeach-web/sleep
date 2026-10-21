<?php

namespace WPML\TM\ATE\ClonedSites;

class InProgressJobsCanceller {

	private $ateJobsRepository;

	private $updateJobs;

	private $translationJobFactory;

	public function __construct(
		\WPML_TM_ATE_Job_Repository $ateJobsRepository,
		\WPML_TP_Sync_Update_Job $updateJobs,
		\WPML_Translation_Job_Factory $translationJobFactory
	) {
		$this->ateJobsRepository     = $ateJobsRepository;
		$this->updateJobs            = $updateJobs;
		$this->translationJobFactory = $translationJobFactory;
	}

	public function cancel(): int {
		$jobsInProgress = $this->ateJobsRepository->get_jobs_to_sync()->filter(
			function ( $job ) {
				return (bool) $job->get_editor_job_id() && ! $this->isCompletedRecord( $job );
			}
		);

		foreach ( $jobsInProgress as $jobInProgress ) {
			$this->updateJobs->cancel( $jobInProgress );
			$this->translationJobFactory->delete_job_data( $jobInProgress->get_translate_job_id() );
		}

		return count( $jobsInProgress );
	}

	private function isCompletedRecord( $job ) {
		return \WPML\TM\Jobs\TranslationRecord::isCompletedJobId( (int) $job->get_translate_job_id() );
	}
}
