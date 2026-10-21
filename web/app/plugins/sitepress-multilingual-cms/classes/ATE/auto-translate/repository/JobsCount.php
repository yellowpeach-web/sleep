<?php

namespace WPML\TM\ATE\AutoTranslate\Repository;

use WPML\TM\ATE\Jobs;
use WPML\Translation\TranslateJobErrorServiceFactory;

class JobsCount implements JobsCountInterface {
	private $jobs;

	private $runBaseline;

	public function __construct( Jobs $jobs, ?AutomaticRunBaseline $runBaseline = null ) {
		$this->jobs        = $jobs;
		$this->runBaseline = $runBaseline ?: new AutomaticRunBaseline();
	}

	public function get(): array {
		$allAutomaticCount = $this->jobs->getCountOfAutomaticInProgress( true );

		return [
			'allCount'                          => $this->jobs->getCountOfInProgress(),
			'allAutomaticCount'                 => $allAutomaticCount,
			'automaticWithoutLongstandingCount' => $this->jobs->getCountOfAutomaticInProgress( false ),
			'automaticRunTotal'                 => $this->runBaseline->observe( $allAutomaticCount ),
			'needsReviewCount'                  => $this->jobs->getCountOfNeedsReview(),
			'unsolvableJobsCount'               => TranslateJobErrorServiceFactory::create()->getCount(),
		];
	}

}
