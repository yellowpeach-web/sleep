<?php

namespace WPML\TM\Settings;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use WPML\FP\Either;

class CountPageUrlSlugChanges implements IHandler {

	const MAX_POSTS           = 500;
	const POSTS_PER_PAGE      = 50;
	const TIME_BUDGET_SECONDS = 8;

	private $affectedPosts;

	private $rewrite;

	public function __construct( AffectedPostsQuery $affectedPosts, ?PageUrlSlugRewrite $rewrite = null ) {
		$this->affectedPosts = $affectedPosts;
		$this->rewrite       = $rewrite ?: new PageUrlSlugRewrite();
	}

	public function run( Collection $data ) {
		$transition = (string) $data->get( 'transition', '' );

		if ( ! PageUrlSlugRewrite::isRewrite( $transition ) ) {
			return Either::of( $this->finish( $this->start(), $transition ) );
		}

		$acc = $this->walkWithinBudget( $transition );
		if ( null === $acc ) {
			return Either::of( $this->handOver( $transition ) );
		}

		return Either::of( $this->finish( $acc, $transition ) );
	}

	public function planWithinBudget( $transition ) {
		if ( ! PageUrlSlugRewrite::isRewrite( $transition ) ) {
			return [];
		}

		$acc = $this->walkWithinBudget( (string) $transition );

		return null === $acc ? null : $acc['ids'];
	}

	private function walkWithinBudget( $transition ) {
		$acc        = $this->start();
		$started    = microtime( true );
		$walked     = 0;
		$lastPostId = 0;

		do {
			$postIds = $this->affectedPosts->pageTranslated( $lastPostId, self::POSTS_PER_PAGE );

			foreach ( $postIds as $postId ) {
				if ( $walked >= self::MAX_POSTS || microtime( true ) - $started > self::TIME_BUDGET_SECONDS ) {
					return null;
				}
				$walked++;
				$this->accumulate( $transition, (int) $postId, $acc );
			}

			$lastPostId = $postIds ? max( $postIds ) : 0;
		} while ( $postIds );

		return $acc;
	}

	public function start() {
		return [ 'translations' => 0, 'posts' => 0, 'ids' => [] ];
	}

	public function accumulate( $transition, $postId, array &$acc ) {
		$changes = $this->rewrite->plan( $postId, $transition );
		if ( $changes ) {
			$acc['translations'] += count( $changes );
			$acc['posts']++;
			if ( ! isset( $acc['ids'] ) || ! is_array( $acc['ids'] ) ) {
				$acc['ids'] = [];
			}
			foreach ( $changes as $change ) {
				$acc['ids'][] = (int) $change['id'];
			}
		}
	}

	public function finish( array $acc, $transition = '' ) {
		if ( PageUrlSlugRewrite::isRewrite( $transition ) ) {
			PageUrlSlugPlan::save( (string) $transition, isset( $acc['ids'] ) ? (array) $acc['ids'] : [] );
		}

		return [ 'estimated' => true, 'translations' => (int) $acc['translations'], 'posts' => (int) $acc['posts'] ];
	}

	protected function handOver( $transition ) {
		$answer = [ 'estimated' => false, 'translations' => 0, 'posts' => 0 ];

		try {
			$service = \WPML\Container\make( \WPML\Core\BackgroundTask\Service\BackgroundTaskService::class );
		} catch ( \Throwable $e ) {
			return $answer;
		}

		$task = $service->add(
			\WPML\Container\make( CountSettingsChange::class ),
			wpml_collect(
				[
					'count'      => CountSettingsChange::COUNT_SLUG_CHANGES,
					'transition' => $transition,
					'lastPostId' => 0,
					'acc'        => $this->start(),
				]
			)
		);

		$answer['counting'] = true;
		$answer['taskId']   = $task ? (int) $task->getTaskId() : null;
		$answer['total']    = $this->affectedPosts->countTranslated();

		return $answer;
	}

	public function pageOf( $lastPostId, $limit ) {
		return $this->affectedPosts->pageTranslated( (int) $lastPostId, (int) $limit );
	}

	public function total() {
		return $this->affectedPosts->countTranslated();
	}
}
