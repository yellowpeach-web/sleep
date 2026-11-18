<?php

namespace WPML\TM\ATE\Review;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use WPML\Core\Component\Translation\Application\Service\AutomaticJobsCancellation\ReleaseLedger;
use WPML\Core\Component\Translation\Application\Service\AutomaticJobsCancellation\ReleaseSummary;
use WPML\FP\Cast;
use WPML\FP\Fns;
use WPML\FP\Obj;
use WPML\LIB\WP\Post;
use WPML\LIB\WP\User;
use WPML\TM\API\Job\Map;
use WPML\TM\API\Jobs;
use WPML\TM\Jobs\JobLog;
use function WPML\FP\pipe;

class Cancel implements IHandler {

	public function run( Collection $data ) {
		$jobIds       = $data->get( 'jobsIds' );
		$deleteDrafts = $data->get( 'deleteDrafts' );

		$getJob = function ( $jobId ) {
			return Jobs::get( $jobId ) ?: TermJob::get( (int) $jobId );
		};

		$reviewJobs = wpml_collect( $jobIds )
			->map( $getJob )
			->filter( ReviewStatus::doesJobNeedReview() );

		if ( ! User::canManageTranslations() ) {
			$reviewJobs = $reviewJobs->filter( function ( $job ) {
				return (int) Obj::prop( 'translator_id', $job ) === get_current_user_id();
			} );
		}

		if ( $reviewJobs->count() ) {
			return JobLog::runInGroup(
				JobLog::GROUP_ID_CANCELS,
				'Cancel jobs (review cancelled)',
				function () use ( $reviewJobs, $deleteDrafts ) {
					return $this->cancelReviewJobs( $reviewJobs, $deleteDrafts );
				}
			);
		}

		return [
			'jobIds'  => [],
			'release' => ( new ReleaseSummary() )->toArray(),
		];
	}

	private function cancelReviewJobs( Collection $reviewJobs, $deleteDrafts ) {
		$releaseMark = ReleaseLedger::instance()->mark();

		$reviewJobs->map( Obj::prop( 'job_id' ) )
		           ->map( Jobs::clearReviewStatus() );

		$reviewJobs->filter( [ TermJob::class, 'isTermBundle' ] )
		           ->each( function ( $job ) {
			           $this->bundleReview()->cancel( (int) Obj::prop( 'rid', $job ) );
		           } );

		$leftOpen = function ( $job ) {
			return TermJob::isTermBundle( $job ) ? false : ( TermJob::isTermJob( $job ) || $this->isDraft( $job ) );
		};
		$closed = $reviewJobs->reject( $leftOpen )
		                     ->filter( function ( $job ) {
			                     return $this->translationRecord()->closeOpenOfRecord( (int) Obj::prop( 'job_id', $job ) );
		                     } )
		                     ->pluck( 'job_id' )
		                     ->toArray();

		if ( $deleteDrafts ) {
			$postJobs = $reviewJobs->reject( function ( $job ) {
				                       return TermJob::isTermJob( $job );
			                       } )
			                       ->reject( function ( $job ) use ( $closed ) {
				                       return in_array( Obj::prop( 'job_id', $job ), $closed, true );
			                       } );
			if ( $postJobs->count() ) {
				$this->deleteDrafts( $postJobs );
			}
		}

		return [
			'jobIds'  => $reviewJobs->pluck( 'job_id' )->map( Cast::toInt() )->values()->toArray(),
			'release' => ReleaseLedger::instance()->summaryFrom( $releaseMark )->toArray(),
		];
	}

	public function isDraft( $job ) {
		$postId = (int) Jobs::getTranslatedPostId( $job );

		return $postId && 'draft' === Post::getStatus( $postId );
	}

	protected function translationRecord() {
		return new \WPML\TM\Jobs\TranslationRecord();
	}

	protected function bundleReview() {
		return new TermBundleReview();
	}

	private function deleteDrafts( Collection $reviewJobs ) {
		$doCancelJobsAction = function ( Collection $jobIds ) {
			$getJobEntity = function ( $jobId ) {
				return wpml_tm_get_jobs_repository()->get_job( Map::ridFromJobId( $jobId ), \WPML_TM_Job_Entity::POST_TYPE );
			};

			$jobEntities = $jobIds->map( $getJobEntity )->toArray();
			do_action( 'wpml_tm_jobs_cancelled', $jobEntities, \WPML\TM\ATE\Cancel\CancelSource::DASHBOARD );
		};

		$getTranslatedId = Fns::memorize( pipe( Jobs::get(), Jobs::getTranslatedPostId() ) );

		$deleteTranslatedPost = pipe( $getTranslatedId, Post::delete() );

		$draftJobs = $reviewJobs->filter( [ $this, 'isDraft' ] )->map( Obj::prop( 'job_id' ) );

		$reviewJobs = $reviewJobs->map( Obj::prop( 'job_id' ) );

		$draftJobs->map( Jobs::setNotTranslatedStatus() )
		          ->map( Jobs::clearTranslated() );

		$doCancelJobsAction( $reviewJobs );

		$draftJobs->map( Fns::tap( $deleteTranslatedPost ) )
		          ->map( Jobs::delete() );
	}
}
