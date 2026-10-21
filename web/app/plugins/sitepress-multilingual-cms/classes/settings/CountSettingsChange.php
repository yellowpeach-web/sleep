<?php

namespace WPML\TM\Settings;

use WPML\BackgroundTask\AbstractTaskEndpoint;
use WPML\Collect\Support\Collection;
use WPML\Core\BackgroundTask\Command\UpdateBackgroundTask;
use WPML\Core\BackgroundTask\Model\BackgroundTask;
use WPML\Core\BackgroundTask\Service\BackgroundTaskService;
use WPML\FP\Obj;

class CountSettingsChange extends AbstractTaskEndpoint {

	const COUNT_ESTIMATE     = 'estimate';
	const COUNT_SLUG_CHANGES = 'slug-changes';

	const POSTS_PER_REQUEST = 200;
	const LOCK_TIME         = 60;

	private $estimate;

	private $slugChanges;

	public function __construct(
		EstimateReapplyCost $estimate,
		CountPageUrlSlugChanges $slugChanges,
		UpdateBackgroundTask $updateBackgroundTask,
		BackgroundTaskService $backgroundTaskService
	) {
		$this->estimate    = $estimate;
		$this->slugChanges = $slugChanges;

		parent::__construct( $updateBackgroundTask, $backgroundTaskService );
	}

	public function runBackgroundTask( BackgroundTask $task ) {
		$payload    = (array) $task->getPayload();
		$count      = (string) Obj::propOr( self::COUNT_ESTIMATE, 'count', $payload );
		$lastPostId = (int) Obj::propOr( 0, 'lastPostId', $payload );
		$acc        = (array) Obj::propOr( [], 'acc', $payload );
		$fromExtras = false;

		if ( self::COUNT_SLUG_CHANGES === $count ) {
			$transition = (string) Obj::propOr( '', 'transition', $payload );
			$acc        = $acc ?: $this->slugChanges->start();
			$postIds    = $this->slugChanges->pageOf( $lastPostId, self::POSTS_PER_REQUEST );

			foreach ( $postIds as $postId ) {
				$this->slugChanges->accumulate( $transition, (int) $postId, $acc );
			}

			if ( ! $postIds ) {
				$payload['answer'] = $this->slugChanges->finish( $acc, $transition );
			}
		} else {
			$customFields = array_values( array_filter( array_map( 'strval', (array) Obj::propOr( [], 'customFields', $payload ) ) ) );
			$extraPostIds = array_values( array_map( 'intval', (array) Obj::propOr( [], 'extraPostIds', $payload ) ) );
			$extrasDone   = (int) Obj::propOr( 0, 'extrasDone', $payload );
			$acc          = $acc ?: $this->estimate->start();
			$postIds      = Obj::propOr( false, 'coreDone', $payload ) ? [] : $this->estimate->pageOf( $customFields, $lastPostId, self::POSTS_PER_REQUEST );
			if ( ! $postIds ) {
				$payload['coreDone']   = true;
				$postIds               = array_slice( $extraPostIds, $extrasDone, self::POSTS_PER_REQUEST );
				$payload['extrasDone'] = $extrasDone + count( $postIds );
				$fromExtras            = true;
			}
			$carriers = $this->estimate->carriersOf( $customFields, $postIds, $acc );

			foreach ( $postIds as $postId ) {
				if ( ! $this->estimate->accumulate( $customFields, (int) $postId, $acc, isset( $carriers[ (int) $postId ] ) ? $carriers[ (int) $postId ] : null ) ) {
					$payload['answer'] = $this->estimate->notEstimated();
					$payload['acc']    = $acc;
					$task->setPayload( $payload );
					$task->setTotalCount( $task->getCompletedCount() );
					$task->finish();

					return $task;
				}
			}

			if ( ! $postIds ) {
				$payload['answer'] = $this->estimate->finish( $acc );
			}
		}

		$payload['acc'] = $acc;

		if ( $postIds ) {
			if ( empty( $fromExtras ) ) {
				$payload['lastPostId'] = max( $postIds );
			}
			$task->setPayload( $payload );
			$task->addCompletedCount( count( $postIds ) );
			$task->setRetryCount( 0 );
		} else {
			$task->setPayload( $payload );
			$task->setTotalCount( $task->getCompletedCount() );
			$task->finish();
		}

		return $task;
	}

	public function getDescription( Collection $data ) {
		if ( self::COUNT_SLUG_CHANGES === (string) $data->get( 'count' ) ) {
			/* translators: Title of the progress notification shown while WPML counts the existing translations whose URL a changed Page URL setting would change. */
			return __( 'Counting the existing translations whose URL would change.', 'sitepress' );
		}

		/* translators: Title of the progress notification shown while WPML counts the words a changed translation setting sends. */
		return __( 'Counting the words the changed translation setting sends.', 'sitepress' );
	}

	public function getTotalRecords( Collection $data ) {
		if ( self::COUNT_SLUG_CHANGES === (string) $data->get( 'count' ) ) {
			return $this->slugChanges->total();
		}

		$customFields = array_values( array_filter( array_map( 'strval', (array) $data->get( 'customFields', [] ) ) ) );
		$extraPostIds = array_values( array_map( 'intval', (array) $data->get( 'extraPostIds', [] ) ) );

		return $this->estimate->total( $customFields, $extraPostIds );
	}

	public function isDisplayed() {
		return false;
	}
}
