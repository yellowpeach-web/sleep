<?php

namespace WPML\TM\Settings;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use WPML\Element\API\Languages;
use WPML\FP\Either;
use WPML\FP\Obj;

class EstimateReapplyCost implements IHandler {

	const MAX_POSTS           = 500;
	const POSTS_PER_PAGE      = 50;
	const TIME_BUDGET_SECONDS = 8;

	private $affectedPosts;

	private $eligibility;

	public function __construct( AffectedPostsQuery $affectedPosts, ?ReapplyEligibility $eligibility = null ) {
		$this->affectedPosts = $affectedPosts;
		$this->eligibility   = $eligibility ?: new ReapplyEligibility();
	}

	public function run( Collection $data ) {
		$customFields = array_values( array_filter( array_map( 'strval', (array) $data->get( 'customFields', [] ) ) ) );

		if ( ! $customFields || ! $this->targetLanguages() ) {
			return Either::of( $this->finish( $this->start() ) );
		}

		$extraPostIds = $this->extraPostIds( $customFields, (array) $data->get( 'changes', [] ) );
		$acc          = $this->start();
		$started      = microtime( true );
		$lastPostId   = 0;
		$extrasDone   = 0;
		$coreDone     = false;

		do {
			$postIds = $coreDone ? [] : $this->pageOf( $customFields, $lastPostId, self::POSTS_PER_PAGE );
			if ( $postIds ) {
				$lastPostId = max( $postIds );
			} else {
				$coreDone    = true;
				$postIds     = array_slice( $extraPostIds, $extrasDone, self::POSTS_PER_PAGE );
				$extrasDone += count( $postIds );
			}
			$carriers = $this->carriersOf( $customFields, $postIds, $acc );

			foreach ( $postIds as $postId ) {
				if ( $acc['posts'] >= self::MAX_POSTS || microtime( true ) - $started > self::TIME_BUDGET_SECONDS ) {
					return Either::of( $this->handOver( $customFields, $extraPostIds ) );
				}

				if ( ! $this->accumulate( $customFields, (int) $postId, $acc, isset( $carriers[ (int) $postId ] ) ? $carriers[ (int) $postId ] : null ) ) {
					return Either::of( $this->notEstimated() );
				}
			}
		} while ( $postIds );

		return Either::of( $this->finish( $acc ) );
	}

	public function extraPostIds( array $customFields, array $changes ) {
		if ( ! $changes ) {
			return [];
		}
		$changeSet = PreferenceChangeSet::fromArray( $changes );
		if ( $changeSet->isEmpty() ) {
			return [];
		}

		return $this->affectedPosts->outsideTheSet( $customFields, PreferenceReapply::extraPostIds( $changeSet ) );
	}

	public function pageOf( array $customFields, $lastPostId, $limit ) {
		return $this->affectedPosts->page( $customFields, (int) $lastPostId, (int) $limit );
	}

	public function carriersOf( array $customFields, array $postIds, array &$acc ) {
		$carriers = CarrierPosts::of( $postIds );
		$shared   = CarrierPosts::shared( $carriers );
		$langs    = array_keys( (array) $acc['wordsPerLang'] );

		foreach ( $shared ? $this->affectedPosts->affectedAmong( $customFields, $shared ) : [] as $carrierId ) {
			if ( ! isset( $acc['carriers'][ $carrierId ] ) ) {
				$eligibility                    = $this->eligibility->forPost( $carrierId );
				$acc['carriers'][ $carrierId ] = array_values( array_intersect( $langs, $eligibility['translated'], $eligibility['sendable'] ) );
			}
		}

		return $carriers;
	}

	public function total( array $customFields, array $extraPostIds = [] ) {
		return $this->affectedPosts->countAffected( $customFields ) + count( $extraPostIds );
	}

	public function start() {
		return [ 'posts' => 0, 'held' => 0, 'wordsPerLang' => array_fill_keys( $this->targetLanguages(), 0 ) ];
	}

	public function accumulate( array $customFields, $postId, array &$acc, $carrierId = null ) {
		$langs     = array_keys( (array) $acc['wordsPerLang'] );
		$carrierId = (int) $carrierId > 0 ? (int) $carrierId : (int) $postId;

		if ( $carrierId !== (int) $postId && isset( $acc['carriers'][ $carrierId ] ) ) {
			$fieldWords = $this->wordsOfFields( $postId, $customFields );
			foreach ( (array) $acc['carriers'][ $carrierId ] as $lang ) {
				$acc['wordsPerLang'][ $lang ] += $fieldWords;
			}

			return true;
		}

		$acc['posts']++;

		$eligibility = $this->eligibility->forPost( $carrierId );
		$translated  = array_values( array_intersect( $langs, $eligibility['translated'] ) );
		$sendable    = array_values( array_intersect( $translated, $eligibility['sendable'] ) );
		if ( $carrierId !== (int) $postId ) {
			$acc['carriers'][ $carrierId ] = $sendable;
		}
		if ( ! $translated ) {
			return true;
		}
		if ( count( $sendable ) < count( $translated ) ) {
			$acc['held']++;
		}
		if ( ! $sendable ) {
			return true;
		}

		$fieldWords  = $this->wordsOfFields( $postId, $customFields );
		$stale       = array_values( array_intersect( $sendable, (array) Obj::propOr( [], 'stale', $eligibility ) ) );
		$outstanding = $stale ? $this->outstandingWords( $this->wordsToTranslateService(), $carrierId, $stale ) : [];
		if ( null === $outstanding ) {
			return false;
		}

		foreach ( $sendable as $lang ) {
			$acc['wordsPerLang'][ $lang ] += $fieldWords + ( isset( $outstanding[ $lang ] ) ? (int) $outstanding[ $lang ] : 0 );
		}

		return true;
	}

	public function finish( array $acc ) {
		$wordsPerLang = array_filter( (array) $acc['wordsPerLang'] );
		$service      = $this->wordsToTranslateService();
		$credits      = $service ? $this->creditsPerLanguage( $service, $wordsPerLang ) : null;

		return [
			'estimated'   => true,
			'posts'       => (int) $acc['posts'],
			'words'       => array_sum( $wordsPerLang ),
			'credits'     => null === $credits ? null : array_sum( $credits ),
			'languages'   => count( $wordsPerLang ),
			'perLanguage' => $this->perLanguage( $wordsPerLang, $credits ),
			'held'        => (int) $acc['held'],
		];
	}

	public function notEstimated() {
		return [ 'estimated' => false, 'posts' => 0, 'words' => 0, 'credits' => null, 'languages' => 0, 'perLanguage' => [], 'held' => 0 ];
	}

	private function perLanguage( array $wordsPerLang, $creditsPerLang ) {
		$rows = [];
		foreach ( $wordsPerLang as $lang => $words ) {
			$rows[] = [
				'code'    => (string) $lang,
				'words'   => (int) $words,
				'credits' => null === $creditsPerLang ? null : ( isset( $creditsPerLang[ $lang ] ) ? (int) $creditsPerLang[ $lang ] : 0 ),
			];
		}

		return $rows;
	}

	protected function handOver( array $customFields, array $extraPostIds = [] ) {
		$answer  = $this->notEstimated();
		$service = $this->backgroundTaskService();
		if ( ! $service ) {
			return $answer;
		}

		$task = $service->add(
			\WPML\Container\make( CountSettingsChange::class ),
			wpml_collect(
				[
					'count'        => CountSettingsChange::COUNT_ESTIMATE,
					'customFields' => $customFields,
					'extraPostIds' => array_values( $extraPostIds ),
					'extrasDone'   => 0,
					'lastPostId'   => 0,
					'acc'          => $this->start(),
				]
			)
		);

		$answer['counting'] = true;
		$answer['taskId']   = $task ? (int) $task->getTaskId() : null;
		$answer['total']    = $this->total( $customFields, $extraPostIds );

		return $answer;
	}

	protected function backgroundTaskService() {
		try {
			return \WPML\Container\make( \WPML\Core\BackgroundTask\Service\BackgroundTaskService::class );
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	private function creditsPerLanguage( $service, array $wordsPerLang ) {
		$sourceLang = $this->defaultLanguage();
		$credits    = [];
		foreach ( $wordsPerLang as $lang => $words ) {
			if ( ! $words ) {
				continue;
			}
			try {
				$perWord = $service->getCostsPerWordForLang( (string) $lang, $sourceLang );
			} catch ( \Throwable $e ) {
				return null;
			}
			if ( ! is_numeric( $perWord ) ) {
				return null;
			}
			$credits[ $lang ] = (int) ceil( $words * (float) $perWord );
		}

		return $credits;
	}

	protected function targetLanguages() {
		return array_values( (array) Languages::getSecondaryCodes() );
	}

	protected function wordsOfFields( $postId, array $fieldNames ) {
		$words = 0;
		foreach ( $fieldNames as $field ) {
			foreach ( (array) get_post_meta( $postId, $field, false ) as $value ) {
				$words += $this->wordsOf( is_scalar( $value ) ? (string) $value : (string) wp_json_encode( $value ) );
			}
		}

		return $words;
	}

	protected function outstandingWords( $service, $postId, array $langs ) {
		$words = array_fill_keys( $langs, 0 );
		if ( ! $service ) {
			return $words;
		}

		try {
			$item = $service->getForPost( $postId, $langs, false );
		} catch ( \Throwable $e ) {
			return null;
		}

		foreach ( $langs as $lang ) {
			$words[ $lang ] = (int) $item->getWordsToTranslate( $lang );
		}

		return $words;
	}

	protected function wordsOf( $text ) {
		if ( '' === trim( $text ) ) {
			return 0;
		}

		$calculator = new \WPML_TM_Word_Calculator( new \WPML_PHP_Functions() );

		return (int) $calculator->count_words( $text, $this->defaultLanguage() );
	}

	protected function defaultLanguage() {
		return (string) Languages::getDefaultCode();
	}

	protected function wordsToTranslateService() {
		$dic = isset( $GLOBALS['wpml_dic'] ) ? $GLOBALS['wpml_dic'] : null;
		if ( ! $dic || ! method_exists( $dic, 'make' ) ) {
			return null;
		}

		try {
			return $dic->make( \WPML\Core\Component\WordsToTranslate\Application\Service\WordsToTranslateService::class );
		} catch ( \Throwable $e ) {
			return null;
		}
	}
}
