<?php

namespace WPML\TM\ATE\TranslateEverything;

use WPML\API\PostTypes;
use WPML\Core\SharedKernel\Component\Post\Domain\TranslateEverythingScope;
use WPML\Element\API\Languages;
use WPML\FP\Cast;
use WPML\FP\Fns;
use WPML\FP\Lst;
use WPML\FP\Obj;
use WPML\FP\Relation;
use WPML\Setup\Option;
use WPML\TM\API\ATE\CachedLanguageMappings;
use WPML\TM\API\ATE\LanguageMappings;
use WPML\TM\Jobs\JobLog;
use WPML\Core\Component\Translation\Domain\Priority\Tier;
use WPML\TM\AutomaticTranslation\Actions\Actions;
use function WPML\FP\pipe;

class UntranslatedPosts extends AbstractUntranslatedElements{

	const PRIORITY_QUEUE_TTL            = 2 * 3600;
	const PRIORITY_QUEUE_KEY_PREFIX     = 'wpml_tea_priority_queue_';
	const PRIORITY_QUEUE_VERSION_OPTION = 'wpml_tea_priority_queue_version';

	const EXCLUDED_IDS_LOG_CAP = 20;

	const EXCLUDED_IDS_TRACK_CAP = 500;

	private $priorityQueues = [];

	public function getTypeWithLanguagesToProcess() {
		$postTypes = $this->getPostTypesToTranslate(
			$this->getTypes(),
			$this->getEligibleLanguageCodes( true )
		);

		return wpml_collect( $postTypes )
			->sortBy( function ( $item ) {
				return self::getPostTypeTier( $item[0] );
			} )
			->first();
	}

	private function getPostTypesToTranslate( array $postTypes, array $targetLanguages ) {
		$completed                               = $this->getCompleted();
		$getLanguageCodesNotCompletedForPostType = pipe( Obj::propOr( [], Fns::__, $completed ), Lst::diff( $targetLanguages ) );

		$getPostTypesToTranslate = pipe(
			Fns::map( function ( $postType ) use ( $getLanguageCodesNotCompletedForPostType ) {
				return [ $postType, $getLanguageCodesNotCompletedForPostType( $postType ) ];
			} ),
			Fns::filter( pipe( Obj::prop( 1 ), Lst::length() ) )
		);

		return $getPostTypesToTranslate( $postTypes );
	}

	public function getElementsToProcess( $languages, $type, $queueSize ) {
		if ( empty( $languages ) ) {
			return [];
		}

		$queueSize      = max( 1, (int) $queueSize );
		$queue          = $this->getPriorityQueue( $languages, $type );
		$kept           = [];
		$keptIds        = [];
		$keptCount      = 0;
		$firstKeptIndex = null;
		$rebuilt        = false;
		$excludedIds    = [];
		$index          = (int) $queue['cursor'];

		while ( $keptCount < $queueSize ) {
			$window = array_slice( $queue['ids'], $index, $queueSize );

			if ( ! $window ) {
				if ( $rebuilt ) {
					break;
				}
				$rebuilt = true;
				$fresh   = $this->buildPriorityQueue( $languages, $type );
				if ( ! array_diff( $fresh['ids'], $queue['ids'] ) ) {
					break;
				}
				$queue          = $fresh;
				$index          = 0;
				$firstKeptIndex = null;
				continue;
			}

			$fetch = array_values( array_diff( $window, $keptIds ) );
			$rows  = $fetch ? $this->fetchRowsForElements( $languages, $type, $fetch ) : [];

			foreach ( $rows as $row ) {
				if ( ! apply_filters( 'wpml_exclude_post_from_auto_translate', false, (int) $row[0] ) ) {
					if ( null === $firstKeptIndex ) {
						$firstKeptIndex = $index + (int) array_search( (int) $row[0], $window, true );
					}
					$kept[]    = $row;
					$keptIds[] = (int) $row[0];
					$keptCount++;
				} elseif ( count( $excludedIds ) < self::EXCLUDED_IDS_TRACK_CAP ) {
					$excludedIds[ (int) $row[0] ] = true;
				}
			}

			$index += count( $window );
		}

		$queue['cursor'] = null === $firstKeptIndex ? min( $index, count( $queue['ids'] ) ) : $firstKeptIndex;
		$this->savePriorityQueue( $languages, $type, $queue );

		if ( $excludedIds ) {
			$excludedIds = array_keys( $excludedIds );
			JobLog::add( 'tea_posts_excluded_by_filter', [
				'type'           => $type,
				'excluded_count' => count( $excludedIds ),
				'count_capped'   => count( $excludedIds ) >= self::EXCLUDED_IDS_TRACK_CAP,
				'post_ids'       => array_slice( $excludedIds, 0, self::EXCLUDED_IDS_LOG_CAP ),
			] );
		}

		return array_slice( $kept, 0, $queueSize );
	}

	private function getPriorityQueue( $languages, $type ) {
		$key = $this->getPriorityQueueKey( $languages, $type );

		if ( isset( $this->priorityQueues[ $key ] ) ) {
			return $this->priorityQueues[ $key ];
		}

		$queue = get_transient( $key );
		if ( ! is_array( $queue ) || ! isset( $queue['ids'], $queue['cursor'] ) || ! is_array( $queue['ids'] ) ) {
			$queue = $this->buildPriorityQueue( $languages, $type );
			$this->savePriorityQueue( $languages, $type, $queue );
		}

		$this->priorityQueues[ $key ] = $queue;

		return $queue;
	}

	private function savePriorityQueue( $languages, $type, array $queue ) {
		$key                          = $this->getPriorityQueueKey( $languages, $type );
		$this->priorityQueues[ $key ] = $queue;
		set_transient( $key, $queue, self::PRIORITY_QUEUE_TTL );
	}

	private function getPriorityQueueKey( $languages, $type ) {
		$languages = array_map( 'strval', $languages );
		sort( $languages );

		return self::PRIORITY_QUEUE_KEY_PREFIX . md5( implode( '|', [
			$type,
			implode( ',', $languages ),
			(string) Option::getTranslateEverythingPostSinceDate( $type ),
			(string) get_option( self::PRIORITY_QUEUE_VERSION_OPTION, 0 ),
		] ) );
	}

	private function buildPriorityQueue( $languages, $type ) {
		$ids = $this->fetchCandidateIds( $languages, $type );

		if ( count( $ids ) > 1 ) {
			$payload   = \WPML\Translation\AteSyncOrderingServiceFactory::create()
				->getOrderingPayloadArrayForPosts( $ids );
			$positions = isset( $payload['positions'] ) && is_array( $payload['positions'] ) ? $payload['positions'] : [];

			usort(
				$ids,
				function ( $a, $b ) use ( $positions ) {
					$posA = isset( $positions[ (string) $a ] ) ? (int) $positions[ (string) $a ] : PHP_INT_MAX;
					$posB = isset( $positions[ (string) $b ] ) ? (int) $positions[ (string) $b ] : PHP_INT_MAX;

					return $posA === $posB ? $a <=> $b : $posA <=> $posB;
				}
			);
		}

		return [ 'ids' => array_values( $ids ), 'cursor' => 0 ];
	}

	private function discardPriorityQueues() {
		$this->priorityQueues = [];
		update_option( self::PRIORITY_QUEUE_VERSION_OPTION, (int) get_option( self::PRIORITY_QUEUE_VERSION_OPTION, 0 ) + 1, false );
	}

	private function fetchCandidateIds( $languages, $type ) {
		$rows = $this->queryCandidates(
			$languages,
			$type,
			'DISTINCT original_element.element_id',
			'',
			'ORDER BY original_element.element_id',
			[]
		);

		return array_map( 'intval', array_column( $rows, 0 ) );
	}

	private function fetchRowsForElements( $languages, $type, array $ids ) {
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		$rows = $this->queryCandidates(
			$languages,
			$type,
			'original_element.element_id, original_element.language_code, languages.code',
			"AND original_element.element_id IN ( {$placeholders} )",
			"ORDER BY FIELD( original_element.element_id, {$placeholders} ), languages.code",
			array_merge( $ids, $ids )
		);

		return Fns::map( Obj::evolve( [ 0 => Cast::toInt() ] ), $rows );
	}

	private function queryCandidates( $languages, $type, $select, $extraWhere, $orderBy, array $extraArgs ) {
		$editorCondition = sprintf(
			'COALESCE( %s, %d ) = 0',
			$this->getPerPostEditorCase(),
			\WPML_TM_Post_Edit_TM_Editor_Mode::is_post_type_using_wp_editor( $type ) ? 1 : 0
		);

		$languagesPart      = $this->buildLanguagesUnion( $languages );
		$acceptableStatuses = ICL_TM_NOT_TRANSLATED . ', ' . ICL_TM_ATE_CANCELLED;

		$oldEditorCondition = $this->buildOldEditorCondition();

		$statusList = $this->buildPostStatusList();

		$cutoffCondition = Cutoff::postSql( 'posts' );

		$discardedJob     = DiscardedAutomaticJob::joinSql( $this->wpdb->prefix, 'translation_status' );
		$notADiscardedJob = DiscardedAutomaticJob::whereSql( 'translation_status' );

		$sql = "
			SELECT {$select}
			FROM {$this->wpdb->prefix}icl_translations original_element
			INNER JOIN ( $languagesPart ) as languages
			LEFT JOIN {$this->wpdb->prefix}icl_translations translations ON translations.trid = original_element.trid AND translations.language_code = languages.code
			LEFT JOIN {$this->wpdb->prefix}icl_translation_status translation_status ON translation_status.translation_id = translations.translation_id
			{$discardedJob}

			INNER JOIN {$this->wpdb->posts} posts ON posts.ID = original_element.element_id

			LEFT JOIN {$this->wpdb->postmeta} postmeta_editor ON postmeta_editor.post_id = posts.ID AND postmeta_editor.meta_key = %s
			LEFT JOIN {$this->wpdb->postmeta} postmeta ON postmeta.post_id = posts.ID AND postmeta.meta_key = %s
			LEFT JOIN {$this->wpdb->postmeta} postmeta_wpml ON postmeta_wpml.post_id = posts.ID AND postmeta_wpml.meta_key = %s

			WHERE original_element.element_type = %s
				AND {$cutoffCondition}
			  	AND original_element.source_language_code IS NULL
			    AND original_element.language_code != languages.code
			    AND ( translation_status.status IS NULL OR translation_status.status IN ({$acceptableStatuses}) OR translation_status.needs_update = 1)
			    {$notADiscardedJob}
			    AND posts.post_status IN ( {$statusList} )
					AND {$editorCondition}
			    {$extraWhere}
			    {$oldEditorCondition}
			{$orderBy}
		";

		$result = $this->wpdb->get_results(
			$this->wpdb->prepare(
				$sql,
				...array_merge(
					[
						\WPML_TM_Post_Edit_TM_Editor_Mode::POST_META_KEY_EDITOR,
						\WPML_TM_Post_Edit_TM_Editor_Mode::POST_META_KEY_USE_NATIVE,
						\WPML_TM_Post_Edit_TM_Editor_Mode::POST_META_KEY_USE_WPML,
						'post_' . $type,
						Option::getTranslateEverythingPostSinceDate( $type ),
					],
					$extraArgs
				)
			),
			ARRAY_N
		);

		return is_array( $result ) ? $result : [];
	}

	private function getPerPostEditorCase(): string {
		$editorNative = \WPML_TM_Post_Edit_TM_Editor_Mode::EDITOR_NATIVE;

		return "CASE
			WHEN postmeta_editor.meta_value = '{$editorNative}' THEN 1
			WHEN postmeta_editor.meta_value IS NOT NULL THEN 0
			WHEN postmeta.meta_value = 'yes' AND postmeta_wpml.meta_value IS NULL THEN 1
			WHEN postmeta.meta_value = 'no' THEN 0
			ELSE NULL
		END";
	}

	private function buildPostStatusList(): string {
		return "'publish', 'inherit'";
	}


	public function createTranslationJobs( Actions $actions, array $elements, $type ) {
		return $this->createTranslationJobsFromAllLanguages( $actions, $elements, $type );
	}


	protected function getElementTypePrefix(): string {
		return 'post_';
	}


	public function markPostTypeAsUncompleted( string $type ) {
		$this->updateCompleted(
			function ( array $completed ) use ( $type ) {
				$completed[ $type ] = [];

				return $completed;
			}
		);
		$this->discardPriorityQueues();
	}

	public function markEverythingAsUncompleted() {
		parent::markEverythingAsUncompleted();
		$this->discardPriorityQueues();
	}

	public function markLanguagesAsUncompleted( array $languages ) {
		parent::markLanguagesAsUncompleted( $languages );
		$this->discardPriorityQueues();
	}

	public function markSkippedTypesAsCompleted() {
		$sinceDates = $this->getPostsSinceDates();
		$languages  = Languages::getSecondaryCodes();
		$skipped    = [];

		foreach ( $this->getTypes() as $type ) {
			$sinceDate = $sinceDates[ $type ] ?? null;
			if ( $sinceDate === Option::SINCE_DATE_SKIP_TYPE ) {
				$skipped[] = $type;
			}
		}

		if ( ! $skipped ) {
			return;
		}

		$this->updateCompleted(
			function ( array $completed ) use ( $skipped, $languages ) {
				foreach ( $skipped as $type ) {
					$completed[ $type ] = array_values( array_unique(
						array_merge( $completed[ $type ] ?? [], $languages )
					) );
				}

				return $completed;
			}
		);
	}

	public function isPostTypeProcessedForTypeAndLanguage( string $type, string $languageCode ): bool {
		$completed = $this->getCompleted();
		$completedLanguages = $completed[ $type ] ?? [];

		return in_array( $languageCode, $completedLanguages );
	}


	protected function getPostsSinceDates(): array {
		return Option::getTranslateEverythingPostsSinceDates();
	}

	protected function getCompleted(): array {
		return Option::getTranslateEverythingCompletedPosts();
	}

	protected function getCompletedOptionKey() {
		return Option::TRANSLATE_EVERYTHING_POSTS;
	}

	protected function getTypes(): array {
		return TranslateEverythingScope::offeredOnly( PostTypes::getAutomaticTranslatable() );
	}

	private static function getPostTypeTier( string $postType ): int {
		if ( $postType === 'page' ) {
			return Tier::PAGES_UNDER_HOMEPAGE;
		}
		if ( $postType === 'post' ) {
			return Tier::BLOG_POSTS;
		}
		return Tier::OTHER_CPTS;
	}
}
