<?php

namespace WPML\TM\Settings;

use WPML\Core\BackgroundTask\Model\BackgroundTask;
use WPML\Core\BackgroundTask\Repository\BackgroundTaskRepository;
use WPML\Core\BackgroundTask\Service\BackgroundTaskService;
use WPML\FP\Obj;
use function WPML\Container\make;

class PreferenceReapply {

	private $affectedPosts;

	private $backgroundTaskService;

	private $backgroundTaskRepository;

	private $slugChanges;

	private $reapplyToTerms;

	public function __construct(
		AffectedPostsQuery $affectedPosts,
		BackgroundTaskService $backgroundTaskService,
		BackgroundTaskRepository $backgroundTaskRepository,
		?CountPageUrlSlugChanges $slugChanges = null,
		?ReapplyToTerms $reapplyToTerms = null
	) {
		$this->affectedPosts            = $affectedPosts;
		$this->backgroundTaskService    = $backgroundTaskService;
		$this->backgroundTaskRepository = $backgroundTaskRepository;
		$this->slugChanges              = $slugChanges;
		$this->reapplyToTerms           = $reapplyToTerms;
	}

	public function countAffected( array $fieldNames ): int {
		return $this->affectedPosts->countCarriers( $fieldNames );
	}

	public function countAffectedForChanges( array $changes ): int {
		$changeSet = PreferenceChangeSet::fromArray( $changes );

		return $this->affectedPosts->countCarriersAnd( $changeSet->actingFieldNames(), self::extraPostIds( $changeSet ) );
	}

	public function countAffectedTermsForChanges( array $changes ): int {
		$changeSet = PreferenceChangeSet::fromArray( $changes );

		return $this->reapplyToTerms()->countAffected( $changeSet ) + count( self::extraTermIds( $changeSet ) );
	}

	public function schedule( array $changes, $applyToExisting ) {
		$changeSet = PreferenceChangeSet::fromArray( $changes );
		if ( $changeSet->isEmpty() ) {
			return null;
		}

		PreferenceReapplyConsent::record( $changeSet->namesFor( PreferenceChangeSet::TRANSLATE ) );
		CustomFieldChangeDetector::remove( $changeSet->fieldNames() );

		if ( ! $applyToExisting ) {
			return null;
		}

		$existing = $this->liveTaskFor( $changeSet );
		if ( $existing ) {
			return $existing;
		}

		$acting = $changeSet->actingFieldNames();
		$extraPostIds = $this->affectedPosts->outsideTheSet( $acting, self::extraPostIds( $changeSet ) );
		$extraTermIds = self::extraTermIds( $changeSet );
		if ( ! $this->hasAffectedContent( $changeSet, $extraPostIds, $extraTermIds ) ) {
			return null;
		}

		$endpoint = make( ReapplyPreferenceChanges::class );

		$task = $this->backgroundTaskService->add(
			$endpoint,
			wpml_collect(
				[
					'changes'      => $changeSet->toArray(),
					'extraPostIds' => $extraPostIds,
					'lastPostId'   => 0,
					'processed'    => [],
					'extraTermIds' => $extraTermIds,
					'lastTermId'   => 0,
				]
			)
		);

		return $task ? $task->getTaskId() : null;
	}

	public function countTranslated(): int {
		return $this->affectedPosts->countTranslated();
	}

	public function schedulePageUrl( $applyToExisting, $transition ) {
		$taskId     = null;
		$transition = (string) $transition;

		if ( $applyToExisting && PageUrlSlugRewrite::isRewrite( $transition ) ) {
			$taskId = $this->livePageUrlTaskFor( $transition );
			if ( ! $taskId ) {
				$translationIds = PageUrlSlugPlan::take( $transition );
				if ( null === $translationIds ) {
					$translationIds = $this->slugChanges()->planWithinBudget( $transition );
				}

				$payload = [
					'kind'       => ReapplyPreferenceChanges::KIND_PAGE_URL,
					'transition' => $transition,
					'changes'    => [],
					'lastPostId' => 0,
					'processed'  => [],
				];
				if ( is_array( $translationIds ) ) {
					$payload['translationIds'] = $translationIds;
					$queue                     = (bool) $translationIds;
				} else {
					$queue = (bool) $this->countTranslated();
				}

				if ( $queue ) {
					$endpoint = make( ReapplyPreferenceChanges::class );
					$task     = $this->backgroundTaskService->add( $endpoint, wpml_collect( $payload ) );
					$taskId   = $task ? $task->getTaskId() : null;
				}
			}
		} elseif ( ! $applyToExisting ) {
			PageUrlSlugPlan::clear();
		}

		PageUrlReapplyConsent::record( $applyToExisting, $taskId, $transition );

		return $taskId;
	}

	public function getSummary( $taskId ) {
		$task = $this->backgroundTaskRepository->getByTaskId( $taskId );

		return $task ? ReapplyPreferenceChanges::summaryOf( $task ) : null;
	}

	private function liveTaskFor( PreferenceChangeSet $changeSet ) {
		$live = $this->backgroundTaskRepository->getAllByTaskStatus(
			[ BackgroundTask::TASK_STATUS_PENDING, BackgroundTask::TASK_STATUS_INPROGRESS, BackgroundTask::TASK_STATUS_PAUSED ]
		);

		foreach ( (array) $live as $task ) {
			if ( ReapplyPreferenceChanges::class !== $task->getTaskType() ) {
				continue;
			}
			$payload = (array) $task->getPayload();
			if ( ReapplyPreferenceChanges::KIND_CUSTOM_FIELDS !== ReapplyPreferenceChanges::kindOf( $payload ) ) {
				continue;
			}
			$queued = PreferenceChangeSet::fromArray( (array) Obj::propOr( [], 'changes', $payload ) )->toArray();
			$asked  = $changeSet->toArray();
			ksort( $queued );
			ksort( $asked );
			if ( $queued === $asked ) {
				return (int) $task->getTaskId();
			}
		}

		return null;
	}

	private function livePageUrlTaskFor( $transition ) {
		$live = $this->backgroundTaskRepository->getAllByTaskStatus(
			[ BackgroundTask::TASK_STATUS_PENDING, BackgroundTask::TASK_STATUS_INPROGRESS, BackgroundTask::TASK_STATUS_PAUSED ]
		);

		foreach ( (array) $live as $task ) {
			if ( ReapplyPreferenceChanges::class !== $task->getTaskType() ) {
				continue;
			}
			$payload = (array) $task->getPayload();
			if (
				ReapplyPreferenceChanges::KIND_PAGE_URL === ReapplyPreferenceChanges::kindOf( $payload )
				&& (string) $transition === ReapplyPreferenceChanges::pageUrlTransitionOf( $payload )
			) {
				return (int) $task->getTaskId();
			}
		}

		return null;
	}

	private function slugChanges() {
		if ( ! $this->slugChanges ) {
			$this->slugChanges = make( CountPageUrlSlugChanges::class );
		}

		return $this->slugChanges;
	}

	private function hasAffectedContent( PreferenceChangeSet $changeSet, array $extraPostIds, array $extraTermIds ): bool {
		$acting = $changeSet->actingFieldNames();

		return $extraPostIds
			|| $extraTermIds
			|| ( $acting && $this->countAffected( $acting ) )
			|| $this->reapplyToTerms()->countAffected( $changeSet );
	}

	private function reapplyToTerms() {
		if ( ! $this->reapplyToTerms ) {
			$this->reapplyToTerms = make( ReapplyToTerms::class );
		}

		return $this->reapplyToTerms;
	}

	private static function extraTermIds( PreferenceChangeSet $changeSet ) {
		$termChanges = ReapplyToTerms::termChanges( $changeSet );

		if ( $termChanges->isEmpty() ) {
			return [];
		}

		$termIds = apply_filters( 'wpml_preference_reapply_affected_terms', [], $termChanges->toArray() );

		return array_values( array_unique( array_filter( array_map( 'intval', (array) $termIds ) ) ) );
	}

	public static function extraPostIds( PreferenceChangeSet $changeSet ) {
		$postIds = apply_filters( 'wpml_preference_reapply_affected_posts', [], $changeSet->toArray() );

		return array_values( array_unique( array_filter( array_map( 'intval', (array) $postIds ) ) ) );
	}
}
