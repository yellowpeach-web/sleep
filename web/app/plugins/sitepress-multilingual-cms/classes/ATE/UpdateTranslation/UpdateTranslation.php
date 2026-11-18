<?php

namespace WPML\TM\ATE\UpdateTranslation;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use WPML\FP\Either;
use WPML\FP\Fns;
use WPML\FP\Logic;
use WPML\FP\Lst;
use WPML\FP\Obj;
use WPML\LIB\WP\User;
use WPML\TM\API\ATE;
use WPML\TM\API\Jobs;
use WPML\TM\ATE\Review\ReviewStatus;
use WPML\TM\Jobs\JobLog;
use function WPML\Container\make;
use function WPML\FP\partial;
use function WPML\FP\pipe;

class UpdateTranslation implements IHandler {
	public function run( Collection $data ) {
		$jobId            = (int) $data->get( 'jobId' );
		$suppliedPostId   = $data->get( 'postId' );
		$completedInATE   = $data->get( 'completedInATE' );
		$clickedBackInATE = $data->get( 'clickedBackInATE' );

		$job = Jobs::get( $jobId );

		if ( ! $job ) {
			return Either::left( 'Insufficient permissions' );
		}

		if ( ! ( Jobs::shouldBeATESynced( $job ) || self::isDeliveredPostLeftInReviewEditing( $job ) ) ) {
			return $this->wasAppliedWhileThePageWaited( $job, $jobId, $suppliedPostId, $completedInATE )
				? Either::of( 'already-applied-reload-required' )
				: Either::left( 'Insufficient permissions' );
		}

		$postId = (int) Obj::prop( 'element_id', $job );
		if (
			! $postId
			|| ( null !== $suppliedPostId && (int) $suppliedPostId !== $postId )
			|| ! $this->isAuthorized( $job, $postId )
		) {
			return Either::left( 'Insufficient permissions' );
		}

		return JobLog::runInGroup(
			JobLog::GROUP_ID_MAINTENANCE,
			'ATE editor return (update translation)',
			function () use ( $job, $jobId, $postId, $completedInATE, $clickedBackInATE ) {
				return $this->updateTranslation( $job, $jobId, $postId, $completedInATE, $clickedBackInATE );
			}
		);
	}

	private function updateTranslation( $job, $jobId, $postId, $completedInATE, $clickedBackInATE ) {
		JobLog::add( 'update_translation_returned', [
			'job_id'             => $jobId,
			'post_id'            => $postId,
			'completed_in_ate'   => $completedInATE,
			'clicked_back_in_ate' => $clickedBackInATE,
		] );

		if ( $completedInATE === 'COMPLETED_WITHOUT_CHANGED' || $clickedBackInATE ) {
			return $this->completeWithoutChanges( $jobId );
		}

		$ateAPI           = make( ATE::class );
		$applyTranslation = pipe(
			partial( [ $ateAPI, 'applyTranslation' ], $jobId, $postId ),
			Logic::ifElse(
				Fns::identity(),
				Fns::tap( function () use ( $jobId ) {
					$this->maybeSetNeedsReviewStatus( $jobId );
				} ),
				Fns::identity()
			)
		);

		$hasStatus = function ( $statuses ) {
			return pipe( Obj::prop( 'status_id' ), Lst::includes( Fns::__, $statuses ) );
		};

		$shouldApplyXLIFF = $hasStatus( [
			\WPML_TM_ATE_AMS_Endpoints::ATE_JOB_STATUS_DELIVERING,
			\WPML_TM_ATE_AMS_Endpoints::ATE_JOB_STATUS_TRANSLATED,
			\WPML_TM_ATE_AMS_Endpoints::ATE_JOB_STATUS_EDITED,
		] );

		$applyXLIFF = function ( $ateJob ) use ( $applyTranslation ) {
			$xliff = Obj::prop( 'translated_xliff', $ateJob );
			if ( ! $xliff ) {
				return Either::of( 'underway' );
			}

			return $applyTranslation( $xliff ) ? Either::of( 'applied' ) : Either::left( 'error' );
		};

		$isDelivered              = $hasStatus( [ \WPML_TM_ATE_AMS_Endpoints::ATE_JOB_STATUS_DELIVERED ] );
		$isTranslating            = $hasStatus( [ \WPML_TM_ATE_AMS_Endpoints::ATE_JOB_STATUS_TRANSLATING ] );
		$userClickedCompleteInATE = Fns::always( $completedInATE === 'COMPLETED' );
		$otherwise                = Fns::always( true );

		$deliveredStatus = $completedInATE === 'COMPLETED'
			? 'already-applied-reload-required'
			: 'applied-without-changes';

		$handleATEResult = Logic::cond( [
			[ $shouldApplyXLIFF, $applyXLIFF ],
			[
				$isDelivered,
				function () use ( $job, $jobId, $deliveredStatus ) {
					if ( self::isPostJob( $job ) ) {
						$this->maybeSetNeedsReviewStatus( $jobId );
					}

					return Either::of( $deliveredStatus );
				},
			],
			[ $userClickedCompleteInATE, Fns::always( Either::of( 'underway' ) ) ],
			[ $isTranslating, Fns::always( Either::of( 'in-progress' ) ) ],
			[ Logic::isEmpty(), Fns::always( Either::left( 'error' ) ) ],
			[ $otherwise, Fns::always( Either::of( 'in-progress' ) ) ]
		] );

		return Either::of( $jobId )
		             ->map( [ $ateAPI, 'checkJobStatus' ] )
		             ->chain( $handleATEResult );
	}

	private static function isDeliveredPostLeftInReviewEditing( $job ): bool {
		return ICL_TM_COMPLETE === (int) Obj::prop( 'status', $job )
			&& ReviewStatus::EDITING === Obj::prop( 'review_status', $job )
			&& self::isPostJob( $job )
			&& \WPML_TM_Editors::ATE === Obj::prop( 'editor', $job );
	}

	private function wasAppliedWhileThePageWaited( $job, $jobId, $suppliedPostId, $completedInATE ): bool {
		if (
			'COMPLETED' !== $completedInATE
			|| \WPML_TM_Editors::ATE !== Obj::prop( 'editor', $job )
			|| ! Jobs::isApplied( $jobId )
		) {
			return false;
		}

		$postId = (int) Obj::prop( 'element_id', $job );

		return $postId
			&& ( null === $suppliedPostId || (int) $suppliedPostId === $postId )
			&& $this->isAuthorized( $job, $postId );
	}

	private static function isPostJob( $job ): bool {
		return 'post' === Obj::prop( 'element_type_prefix', $job );
	}

	private function isAuthorized( $job, $postId ) {
		if ( User::canManageTranslations() ) {
			return true;
		}

		$translatorId = (int) Obj::prop( 'translator_id', $job );
		if ( $translatorId && get_current_user_id() !== $translatorId ) {
			return false;
		}

		return current_user_can( 'edit_post', $postId );
	}

	private function completeWithoutChanges( $jobId ) {
		$applyWithoutChanges = pipe(
			Fns::tap( Jobs::setStatus( Fns::__, ICL_TM_COMPLETE ) ),
			Fns::tap( function () use ( $jobId ) {
				$this->maybeSetNeedsReviewStatus( $jobId );
			} )
		);

		return Either::of( $jobId )
		             ->map( $applyWithoutChanges )
		             ->map( Fns::always( 'applied-without-changes' ) );
	}
	public function maybeSetNeedsReviewStatus( $jobId ) {
		$job = Jobs::get( $jobId );
		if ( ReviewStatus::doesJobNeedReview( $job ) ) {
			Jobs::setReviewStatus( $jobId, ReviewStatus::NEEDS_REVIEW );
		}
	}
}
