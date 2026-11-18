<?php

namespace WPML\TM\Dashboard\Taxonomy;

use WPML\Element\API\Languages;
use WPML\LanguageEditor\TranslationPause;
use WPML\TM\ATE\Review\ReviewStatus;
use WPML\TM\ATE\TranslateEverything\TermBundleMembers;
use WPML\TM\OwnedPackages\OwnerJobService;
use WPML\TM\Taxonomy\Job\TermWords;
use WPML\TM\Taxonomy\TranslatableTermMeta;

class TaxonomyDashboardData {

	const WC_SNAPSHOT_META_PREFIX = '_wpml_tax_wc_src_';

	private static $requestMemo = [];

	private static $termReadMemo = [];

	const SNAPSHOT_CHUNK = 250;

	public function get( string $search = '', string $stateToken = '' ): array {
		global $wp_taxonomies, $sitepress, $iclTranslationManagement;

		$taxonomies = is_array( $wp_taxonomies ) ? array_map( 'strval', array_keys( $wp_taxonomies ) ) : [];
		$translated = [];
		if ( $sitepress ) {
			foreach ( $taxonomies as $taxonomy ) {
				if ( $sitepress->is_translated_taxonomy( $taxonomy ) ) {
					$translated[] = $taxonomy;
				}
			}
		}
		$termKeySettings = ( $iclTranslationManagement && isset( $iclTranslationManagement->settings['custom_term_fields_translation'] ) )
			? $iclTranslationManagement->settings['custom_term_fields_translation']
			: [];

		$stateToken = '' !== $stateToken ? $stateToken : self::stateToken();

		$metaKeys = [];
		foreach ( $translated as $taxonomy ) {
			$metaKeys[ $taxonomy ] = TranslatableTermMeta::keys( $taxonomy );
		}
		$storeKey = md5(
			(string) json_encode(
				[
					TaxonomyMetricsStore::generation(),
					$stateToken,
					$translated,
					$termKeySettings,
					$metaKeys,
					$this->getSecondaryLanguages(),
					Languages::getDefaultCode(),
					(bool) \WPML\Setup\Option::shouldTranslateEverything(),
					defined( 'ICL_SITEPRESS_VERSION' ) ? ICL_SITEPRESS_VERSION : '',
				]
			)
		);
		$memoKey  = md5(
			(string) json_encode(
				[
					$search,
					$storeKey,
					function_exists( 'wp_cache_get_last_changed' ) ? wp_cache_get_last_changed( 'terms' ) : '',
					$taxonomies,
					apply_filters( 'wpml_current_language', null ),
				]
			)
		);
		if ( isset( self::$requestMemo[ $memoKey ] ) ) {
			return self::$requestMemo[ $memoKey ];
		}

		$data = $this->build( $search, $storeKey );

		self::$requestMemo = [ $memoKey => $data ];

		return $data;
	}

	public static function forgetRequestMemo() {
		self::$requestMemo   = [];
		self::$termReadMemo = [];
	}

	public static function stateToken(): string {
		global $wpdb;

		$termState = $wpdb->get_row(
			"SELECT COUNT(*) AS cnt,
			MAX(ts.timestamp) AS latest,
			COALESCE( SUM(ts.status), 0 ) AS statusSum,
			COALESCE( SUM(ts.needs_update), 0 ) AS needsUpdateSum
			FROM {$wpdb->prefix}icl_translation_status ts
			INNER JOIN {$wpdb->prefix}icl_translations t ON t.translation_id = ts.translation_id
			WHERE t.element_type LIKE 'tax\\_%'",
			ARRAY_A
		);

		$termPopulation = $wpdb->get_row(
			"SELECT COUNT(*) AS cnt, COALESCE( MAX(translation_id), 0 ) AS maxId
			FROM {$wpdb->prefix}icl_translations
			WHERE element_type LIKE 'tax\\_%'",
			ARRAY_A
		);

		$stringState = [
			'cnt'       => 0,
			'maxId'     => 0,
			'statusSum' => 0,
			'strings'   => 0,
			'maxString' => 0,
		];
		if ( defined( 'WPML_ST_VERSION' ) ) {
			$translations = $wpdb->get_row(
				"SELECT COUNT(*) AS cnt, COALESCE( MAX(id), 0 ) AS maxId, COALESCE( SUM(status), 0 ) AS statusSum
				FROM {$wpdb->prefix}icl_string_translations",
				ARRAY_A
			);
			$strings      = $wpdb->get_row(
				"SELECT COUNT(*) AS cnt, COALESCE( MAX(id), 0 ) AS maxId
				FROM {$wpdb->prefix}icl_strings",
				ARRAY_A
			);
			if ( is_array( $translations ) && is_array( $strings ) ) {
				$stringState = [
					'cnt'       => $translations['cnt'],
					'maxId'     => $translations['maxId'],
					'statusSum' => $translations['statusSum'],
					'strings'   => $strings['cnt'],
					'maxString' => $strings['maxId'],
				];
			}
		}

		$components = array_merge(
			array_values( is_array( $termState ) ? $termState : [] ),
			array_values( is_array( $termPopulation ) ? $termPopulation : [] ),
			array_values( $stringState ),
			[ \WPML\Setup\Option::shouldTranslateEverything() ? '1' : '0' ]
		);

		return md5( implode( '|', array_map( 'strval', $components ) ) );
	}

	private function build( string $search, string $storeKey ): array {
		global $sitepress, $wpdb, $wp_taxonomies;

		$rows                = [];
		$labelRows           = [];
		$hiddenCount         = 0;
		$secondaryLanguages  = $this->getSecondaryLanguages();
		$defaultLanguageCode = Languages::getDefaultCode();
		$labelStatus         = new TaxonomyLabelStatus();
		$isTeaActive         = (bool) \WPML\Setup\Option::shouldTranslateEverything();
		$isStActive          = $labelStatus->isStringTranslationActive();

		if ( ! is_array( $wp_taxonomies ) || ! $sitepress ) {
			return $this->shape( $rows, $labelRows, $hiddenCount, $isTeaActive, $isStActive );
		}

		$storedMetrics = TaxonomyMetricsStore::read( $storeKey );
		$metricsOf     = [];
		$inFlight      = false;

		foreach ( $wp_taxonomies as $slug => $object ) {
			$slug = (string) $slug;
			if ( in_array( $slug, [ 'post_format', 'nav_menu', 'link_category', 'post_status' ], true ) ) {
				continue;
			}

			if ( ! $sitepress->is_translated_taxonomy( $slug ) ) {
				if ( $this->isVisibleTaxonomy( $object ) ) {
					$labelRow = $labelStatus->getRow( $slug, $isTeaActive );
					if ( null !== $labelRow ) {
						$labelRows[] = $labelRow;
					}
					if ( $this->isPublicTaxonomy( $object ) && $this->taxonomyHasTerms( $wpdb, $slug ) ) {
						++$hiddenCount;
					}
				}
				continue;
			}

			$metrics            = isset( $storedMetrics[ $slug ] ) && is_array( $storedMetrics[ $slug ] )
				? $storedMetrics[ $slug ]
				: $this->buildTaxonomyMetrics(
					$wpdb,
					$slug,
					$defaultLanguageCode,
					$secondaryLanguages,
					$isTeaActive
				);
			$metricsOf[ $slug ] = $metrics;
			$languageStates     = $metrics['states'];
			foreach ( $languageStates as $state ) {
				$inFlight = $inFlight || ! empty( $state['inProgress'] );
			}

			$wordCounts        = [];
			$wordCountsAll     = [];
			$untranslatedTerms = [];
			foreach ( $languageStates as $state ) {
				$wordCounts[ $state['code'] ]        = $state['words'];
				$wordCountsAll[ $state['code'] ]     = $state['wordsAll'];
				$untranslatedTerms[ $state['code'] ] = $state['untranslated'];
			}

			$isFinished = ! empty( $languageStates )
				&& array_reduce(
					$languageStates,
					function ( $carry, $state ) {
						return $carry && 'complete' === $state['status'];
					},
					true
				);

			$total      = $metrics['total'];
			$translated = max( $total - ( $untranslatedTerms ? max( $untranslatedTerms ) : 0 ), 0 );

			$rows[] = [
				'taxonomy'            => $slug,
				'label'               => $this->getLabel( $object, $slug ),
				'postTypes'           => $this->getPostTypeLabels( $object ),
				'translated'          => $translated,
				'total'               => $total,
				'words'               => $metrics['words'],
				'hierarchical'        => is_taxonomy_hierarchical( $slug ),
				'languages'           => $languageStates,
				'wordCounts'          => $wordCounts,
				'wordCountsAll'       => $wordCountsAll,
				'untranslatedTerms'   => $untranslatedTerms,
				'isFinished'          => $isFinished,
				'editUrl'             => $this->getEditUrl( $slug ),
			];

			$labelRow = $labelStatus->getRow( $slug, $isTeaActive );
			if ( null !== $labelRow ) {
				$labelRows[] = $labelRow;
			}
		}

		if ( null === $storedMetrics && ! $inFlight ) {
			TaxonomyMetricsStore::write( $storeKey, $metricsOf );
		}

		if ( '' !== $search ) {
			list( $rows, $labelRows ) = $this->applySearch( $wpdb, $rows, $labelRows, $search );
		}

		return $this->shape( $rows, $labelRows, $hiddenCount, $isTeaActive, $isStActive );
	}

	private function applySearch( $wpdb, array $rows, array $labelRows, string $search ) {
		$matchesNeedle = function ( $haystack ) use ( $search ) {
			return false !== stripos( (string) $haystack, $search );
		};

		$termMatches = $this->findTermNameMatches(
			$wpdb,
			array_map(
				function ( $row ) {
					return (string) $row['taxonomy'];
				},
				$rows
			),
			$search
		);

		$filteredRows = [];
		foreach ( $rows as $row ) {
			$row['matchedTerms'] = isset( $termMatches[ $row['taxonomy'] ] ) ? $termMatches[ $row['taxonomy'] ] : [];
			if ( $matchesNeedle( $row['label'] ) || $matchesNeedle( $row['taxonomy'] ) || $row['matchedTerms'] ) {
				$filteredRows[] = $row;
			}
		}

		$filteredLabelRows = [];
		foreach ( $labelRows as $labelRow ) {
			if (
				$matchesNeedle( isset( $labelRow['plural'] ) ? $labelRow['plural'] : '' )
				|| $matchesNeedle( isset( $labelRow['singular'] ) ? $labelRow['singular'] : '' )
				|| $matchesNeedle( isset( $labelRow['taxonomy'] ) ? $labelRow['taxonomy'] : '' )
			) {
				$filteredLabelRows[] = $labelRow;
			}
		}

		return [ $filteredRows, $filteredLabelRows ];
	}

	private function findTermNameMatches( $wpdb, array $taxonomies, string $search ): array {
		if ( ! $taxonomies ) {
			return [];
		}

		$placeholders = implode( ',', array_fill( 0, count( $taxonomies ), '%s' ) );

		$sql = "SELECT tt.taxonomy, t.name
			FROM {$wpdb->prefix}terms t
			INNER JOIN {$wpdb->prefix}term_taxonomy tt ON tt.term_id = t.term_id
			WHERE tt.taxonomy IN ( {$placeholders} )
			AND t.name LIKE %s ESCAPE '|'
			ORDER BY t.name
			LIMIT 100";

		$params = array_merge( $taxonomies, [ \WPML\Utils\LikeEscape::contains( $search ) ] );
		$found = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );

		$byTaxonomy = [];
		foreach ( $found ?: [] as $row ) {
			$byTaxonomy[ (string) $row['taxonomy'] ][] = (string) $row['name'];
		}

		return $byTaxonomy;
	}

	private function taxonomyHasTerms( $wpdb, string $taxonomy ): bool {
		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 1 FROM {$wpdb->prefix}term_taxonomy WHERE taxonomy = %s LIMIT 1",
				$taxonomy
			)
		);
	}

	public function getUntranslatedTermIds( string $taxonomy, string $targetLang, bool $includeCompleted = false ): array {
		return array_keys( $this->getUntranslatedTermsWithSource( $taxonomy, $targetLang, $includeCompleted ) );
	}

	public function getUntranslatedTermsWithSource( string $taxonomy, string $targetLang, bool $includeCompleted = false, bool $excludeLiveJobs = false ): array {
		$rows = $this->selectEligibleTermPairs( false, $taxonomy, [ $targetLang ], $includeCompleted, $excludeLiveJobs, 0, PHP_INT_MAX );

		$terms = [];
		foreach ( $rows as $row ) {
			$terms[ (int) $row['element_id'] ] = (string) $row['language_code'];
		}

		return $terms;
	}

	public function getLastTermTaxonomyId(): int {
		global $wpdb;

		return (int) $wpdb->get_var( "SELECT MAX( term_taxonomy_id ) FROM {$wpdb->term_taxonomy}" );
	}

	public function filterTranslatableLanguages( array $codes ): array {
		return TranslationPause::filterTranslatable( $codes );
	}

	public function getLastTranslateJobId(): int {
		global $wpdb;

		return (int) $wpdb->get_var( "SELECT MAX( job_id ) FROM {$wpdb->prefix}icl_translate_job" );
	}

	public function getEligibleLanguagesOfTerm( string $taxonomy, int $elementId, array $targetLanguages, bool $includeCompleted, bool $excludeLiveJobs, int $jobBoundary ): array {
		$rows = $this->selectEligibleTermPairs( false, $taxonomy, $targetLanguages, $includeCompleted, $excludeLiveJobs, $elementId - 1, $elementId, $jobBoundary );

		return array_values( array_map( 'strval', array_column( $rows, 'target_language' ) ) );
	}

	public function countEligibleTermPairs( string $taxonomy, array $targetLanguages, bool $includeCompleted, bool $excludeLiveJobs, int $lastElementId ): int {
		$rows = $this->selectEligibleTermPairs( true, $taxonomy, $targetLanguages, $includeCompleted, $excludeLiveJobs, 0, $lastElementId );

		return (int) ( $rows[0]['pairs'] ?? 0 );
	}

	public function getEligibleTermPairsWindow(
		string $taxonomy,
		array $targetLanguages,
		bool $includeCompleted,
		bool $excludeLiveJobs,
		int $afterElementId,
		int $lastElementId,
		int $windowSize,
		int $jobBoundary = -1
	): array {
		global $wpdb;

		$originals = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT element_id FROM {$wpdb->prefix}icl_translations
				WHERE element_type = %s
				AND source_language_code IS NULL
				AND element_id > %d
				AND element_id <= %d
				ORDER BY element_id
				LIMIT %d",
				'tax_' . $taxonomy,
				$afterElementId,
				$lastElementId,
				$windowSize
			)
		);
		if ( ! $originals ) {
			return [ 'pairs' => [], 'elementIds' => [], 'lastElementId' => $afterElementId, 'hasMore' => false ];
		}

		$windowEnd = (int) end( $originals );
		$pairs     = [];
		foreach ( $this->selectEligibleTermPairs( false, $taxonomy, $targetLanguages, $includeCompleted, $excludeLiveJobs, $afterElementId, $windowEnd, $jobBoundary ) as $row ) {
			$pairs[] = [
				'elementId'      => (int) $row['element_id'],
				'sourceLanguage' => (string) $row['language_code'],
				'targetLanguage' => (string) $row['target_language'],
			];
		}

		return [
			'pairs'         => $pairs,
			'elementIds'    => array_map( 'intval', $originals ),
			'lastElementId' => $windowEnd,
			'hasMore'       => count( $originals ) === $windowSize,
		];
	}

	private function selectEligibleTermPairs( bool $countOnly, string $taxonomy, array $targetLanguages, bool $includeCompleted, bool $excludeLiveJobs, int $afterElementId, int $lastElementId, int $jobBoundary = -1 ): array {
		global $wpdb;

		$targetLanguages = array_values( array_unique( array_map( 'strval', $targetLanguages ) ) );
		if ( ! $targetLanguages ) {
			return [];
		}

		$columns = $countOnly
			? 'COUNT(*) AS pairs'
			: 'original_element.element_id, original_element.language_code, languages.code AS target_language';

		$languagePlaceholders = implode( ', ', array_fill( 0, count( $targetLanguages ), '%s' ) );

		$statusFilter = $includeCompleted
			? ''
			: 'AND ( translations.translation_id IS NULL OR ts.status != %d OR ts.needs_update = 1 )';

		$liveJobFilter = $excludeLiveJobs
			? 'AND NOT EXISTS (
					SELECT 1 FROM ' . $wpdb->prefix . 'icl_translate_job live
					WHERE live.rid = ts.rid
					AND live.revision IS NULL
					AND COALESCE( live.editor_job_id, 0 ) <> 0
					AND ts.status = %d
				)'
			: '';

		$alreadySentFilter = $jobBoundary >= 0
			? 'AND NOT EXISTS (
					SELECT 1 FROM ' . $wpdb->prefix . 'icl_translate_job sent
					WHERE sent.rid = ts.rid
					AND sent.revision IS NULL
					AND sent.job_id > %d
					AND ( COALESCE( sent.editor_job_id, 0 ) <> 0 OR ts.status = %d )
				)'
			: '';

		$sql = "SELECT {$columns}
				FROM {$wpdb->prefix}icl_translations original_element
				INNER JOIN {$wpdb->prefix}icl_languages languages
					ON languages.code IN ( {$languagePlaceholders} )
				LEFT JOIN {$wpdb->prefix}icl_translations translations
					ON translations.trid = original_element.trid
					AND translations.language_code = languages.code
				LEFT JOIN {$wpdb->prefix}icl_translation_status ts
					ON ts.translation_id = translations.translation_id
				WHERE original_element.element_type = %s
				AND original_element.source_language_code IS NULL
				AND original_element.language_code != languages.code
				AND original_element.element_id > %d
				AND original_element.element_id <= %d
				{$statusFilter}
				{$liveJobFilter}
				{$alreadySentFilter}
				-- wpmldev-8099: a review OPEN IN THE EDITOR is somebody's work in
				-- progress, not a gap to fill. Its row is IN_PROGRESS, which is
				-- also what a sent-but-undelivered row looks like, so without this
				-- the send picked the term up again, superseded the job the
				-- reviewer had open and orphaned their editor tab. Re-sending an
				-- in-flight term job is deliberate (wpmldev-8013); re-sending one
				-- under review is not. Holds for `overwrite` too - that redoes
				-- FINISHED work, and an open review is not finished.
				AND ( ts.review_status IS NULL OR ts.review_status != %s )";

		$params = array_merge( $targetLanguages, [ 'tax_' . $taxonomy, $afterElementId, $lastElementId ] );
		if ( ! $includeCompleted ) {
			$params[] = ICL_TM_COMPLETE;
		}
		if ( $excludeLiveJobs ) {
			$params[] = ICL_TM_IN_PROGRESS;
		}
		if ( $jobBoundary >= 0 ) {
			$params[] = $jobBoundary;
			$params[] = ICL_TM_COMPLETE;
		}
		$params[] = ReviewStatus::EDITING;

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );

		return is_array( $rows ) ? $rows : [];
	}

	public function getTermsPage( string $taxonomy, string $mode, int $page, int $perPage, string $search = '', array $ids = [], ?array $selection = null, array $targetLanguages = [] ): array {
		global $sitepress;

		$source = $this->sourceTerms( $taxonomy );
		if ( $page < 1 || $perPage < 1 || $perPage > 100 || ! in_array( $mode, [ 'flat', 'roots', 'search', 'selected' ], true ) ) {
			throw new \InvalidArgumentException( 'Invalid term page.' );
		}

		$parent       = 'roots' === $mode ? 0 : null;
		$search       = 'search' === $mode ? $search : '';
		$addedParents = [];
		if ( 'selected' === $mode && null !== $selection ) {
			$plan = ( new TaxonomySelectionPlan( $this ) )->build(
				[
					'taxonomy'        => $taxonomy,
					'targetLanguages' => $targetLanguages ?: $this->getSecondaryLanguages(),
					'selection'       => $selection,
				]
			);
			$ids  = $plan['canonical']['selectedIds'];
			if ( ! $ids ) {
				return [ 'items' => [], 'total' => 0 ];
			}
			$addedParents = $plan['addedParents'];
		} elseif ( 'selected' === $mode ) {
			$ids = array_values( array_unique( array_map( 'intval', $ids ) ) );
			if ( ! $ids ) {
				return [ 'items' => [], 'total' => 0 ];
			}
			foreach ( $ids as $id ) {
				if ( ! isset( $source[ $id ] ) ) {
					throw new \InvalidArgumentException( 'Selection contains a term outside the original taxonomy.' );
				}
			}
			$languages = $this->getSecondaryLanguages();
			$statuses  = $this->readTermStatuses( $taxonomy, $source, $languages );
			$include   = array_fill_keys( $ids, true );
			foreach ( $ids as $id ) {
				$parentId = (int) $source[ $id ]['parent'];
				while ( $parentId && isset( $source[ $parentId ] ) ) {
					$complete = true;
					foreach ( $languages as $language ) {
						$complete = $complete && 'complete' === ( $statuses[ $parentId ][ $language ] ?? 'missing' );
					}
					if ( ! $complete ) {
						if ( ! isset( $include[ $parentId ] ) ) {
							$addedParents[ $parentId ] = $parentId;
						}
						$include[ $parentId ] = true;
					}
					$parentId = (int) $source[ $parentId ]['parent'];
				}
			}
			$ids = array_keys( $include );
		}

		$total    = 0;
		$listed   = 'selected' === $mode ? array_flip( $ids ) : [];
		$matchIds = [];
		foreach ( $source as $id => $term ) {
			if ( ( 'roots' !== $mode || 0 === (int) $term['parent'] )
				&& ( 'selected' !== $mode || isset( $listed[ $id ] ) )
				&& ( '' === $search || false !== stripos( $term['name'], $search ) ) ) {
				++$total;
				if ( 'search' === $mode && count( $matchIds ) < 1000 ) {
					$matchIds[] = $id;
				}
			}
		}

		$criteria = new \WPML\Core\Component\Post\Application\Query\Criteria\TaxonomyTermCriteria(
			$taxonomy,
			\WPML\Element\API\Languages::getDefaultCode(),
			$search,
			$perPage,
			( $page - 1 ) * $perPage,
			false,
			'term_taxonomy_id',
			$parent,
			'selected' === $mode ? $ids : [],
			true
		);
		$terms = ( new \WPML\Legacy\Component\Post\Application\Query\TaxonomyQuery( $sitepress ) )->getTerms( $criteria );
		$rows  = array_map(
			function ( $dto ) use ( $source ): array {
				return $source[ (int) $dto->getTtid() ];
			},
			$terms
		);
		$result = [
			'items' => $this->makeTermItems( $taxonomy, $rows, $source ),
			'total' => $total,
		];
		if ( 'roots' === $mode ) {
			$result['totalRoots'] = $total;
		}
		if ( 'search' === $mode ) {
			$result['matchIds']          = $matchIds;
			$result['matchIdsTruncated'] = $total > 1000;
		}
		if ( $addedParents ) {
			$result['addedParents'] = array_values( $addedParents );
			foreach ( $result['items'] as &$item ) {
				$item['addedParent'] = in_array( $item['ttid'], $addedParents, true );
			}
		}

		return $result;
	}

	public function getBranch( string $taxonomy, int $parent ): array {
		$source = $this->sourceTerms( $taxonomy );
		if ( ! isset( $source[ $parent ] ) ) {
			throw new \InvalidArgumentException( 'Invalid original branch.' );
		}
		$languages = $this->getSecondaryLanguages();
		$direct    = array_values( array_filter( $source, function ( array $term ) use ( $parent ): bool {
			return (int) $term['parent'] === $parent;
		} ) );
		$statuses  = $this->readTermStatuses( $taxonomy, $source, $languages );

		return [ 'items' => $this->makeTermItems( $taxonomy, $direct, $source, $statuses ) ]
			+ $this->branchCounts( $this->descendantIds( $this->childrenOf( $source ), $parent ), $statuses, $languages );
	}

	public function getBranches( string $taxonomy, array $parents, string $depth ): array {
		$source = $this->sourceTerms( $taxonomy );
		if ( ! in_array( $depth, [ 'children', 'all' ], true ) ) {
			throw new \InvalidArgumentException( 'Invalid branch depth.' );
		}
		foreach ( $parents as $parent ) {
			if ( ! isset( $source[ $parent ] ) ) {
				throw new \InvalidArgumentException( 'Invalid original branch.' );
			}
		}
		$languages = $this->getSecondaryLanguages();
		$statuses  = $this->readTermStatuses( $taxonomy, $source, $languages );
		$children  = $this->childrenOf( $source );

		$listed      = [];
		$descendants = [];
		foreach ( $parents as $parent ) {
			$descendants[ $parent ] = $this->descendantIds( $children, $parent );
			$listed[ $parent ]      = 'all' === $depth
				? $this->inHierarchyOrder( $children, $parent )
				: ( $children[ $parent ] ?? [] );
		}
		$rows = [];
		foreach ( $listed as $ids ) {
			foreach ( $ids as $id ) {
				$rows[ $id ] = $source[ $id ];
			}
		}
		$items = array_column( $this->makeTermItems( $taxonomy, array_values( $rows ), $source, $statuses ), null, 'ttid' );

		$branches = [];
		foreach ( $parents as $parent ) {
			$branches[ $parent ] = [
				'items' => array_map(
					function ( int $id ) use ( $items ): array {
						return $items[ $id ];
					},
					$listed[ $parent ]
				),
			] + $this->branchCounts( $descendants[ $parent ], $statuses, $languages );
		}

		return [ 'branches' => $branches ];
	}

	private function branchCounts( array $descendants, array $statuses, array $languages ): array {
		$missing = 0;
		foreach ( $descendants as $id ) {
			foreach ( $languages as $language ) {
				if ( 'complete' !== ( $statuses[ $id ][ $language ] ?? 'missing' ) ) {
					++$missing;
					break;
				}
			}
		}

		return [
			'descendantIds'          => array_slice( $descendants, 0, 1000 ),
			'descendantIdsTruncated' => count( $descendants ) > 1000,
			'descendantCount'        => count( $descendants ),
			'missing'                => $missing,
		];
	}

	private function inHierarchyOrder( array $children, int $parent ): array {
		$order = [];
		$seen  = [ $parent => true ];
		$stack = array_reverse( $children[ $parent ] ?? [] );
		while ( $stack ) {
			$id = array_pop( $stack );
			if ( isset( $seen[ $id ] ) ) {
				continue;
			}
			$seen[ $id ] = true;
			$order[]     = $id;
			foreach ( array_reverse( $children[ $id ] ?? [] ) as $child ) {
				$stack[] = $child;
			}
		}

		return $order;
	}

	public function getSelectionFacts( string $taxonomy, array $languages, bool $withChangedWords = false ): array {
		$source = $this->sourceTerms( $taxonomy );
		$words  = $this->wordsOfTerms( $taxonomy, $source );
		$terms  = [];
		foreach ( $source as $id => $term ) {
			$terms[ $id ] = [
				'ttid'           => $id,
				'parent'         => (int) $term['parent'],
				'sourceLanguage' => (string) $term['sourceLanguage'],
				'words'          => $words[ $id ] ?? 0,
			];
		}

		$facts = [
			'terms'    => $terms,
			'statuses' => $this->readTermStatuses( $taxonomy, $source, $languages ),
			'carried'  => $this->readCarriedPairs( $taxonomy, array_keys( $source ), $languages ),
		];
		if ( $withChangedWords ) {
			global $wpdb;

			$stalePairs = [];
			$staleIds   = [];
			foreach ( $facts['statuses'] as $id => $byLanguage ) {
				foreach ( $byLanguage as $language => $status ) {
					if ( 'needs_update' === $status && isset( $source[ $id ] ) ) {
						$stalePairs[ (int) $source[ $id ]['termId'] ][ (string) $language ] = (int) $id;
						$staleIds[ (int) $id ]                                              = true;
					}
				}
			}
			$staleTerms = [];
			foreach ( array_chunk( array_intersect_key( $source, $staleIds ), TranslatableTermMeta::BATCH_CHUNK, true ) as $chunk ) {
				$meta  = TranslatableTermMeta::valuesOfTermsByIds( array_map( 'intval', array_column( $chunk, 'termId' ) ), TranslatableTermMeta::keys( $taxonomy ), $taxonomy );
				$owned = OwnerJobService::ownedStringsOfTerms( array_map( 'intval', array_column( $chunk, 'ttid' ) ) ) ?: [];
				foreach ( $chunk as $id => $term ) {
					$staleTerms[ $id ] = $this->termToCount( $term['name'], $term['description'], $meta[ (int) $term['termId'] ] ?? [], $owned[ $id ] ?? [], $term['sourceLanguage'] );
				}
			}
			$byLanguage            = $this->changedWordsOfStalePairs( $wpdb, $taxonomy, $stalePairs, $staleTerms );
			$facts['changedWords'] = [];
			foreach ( $byLanguage as $language => $byElement ) {
				foreach ( $byElement as $id => $changed ) {
					$facts['changedWords'][ $id ][ $language ] = $changed;
				}
			}
		}

		return $facts;
	}

	private function termToCount( string $name, string $description, array $metaValues, array $ownedStrings, string $sourceLang ): array {
		return [
			'fields' => TermWords::fieldsOf( $name, $description, $metaValues, 0, $ownedStrings ),
			'text'   => TermWords::textOf( $name, $description, $metaValues ),
			'lang'   => $sourceLang,
		];
	}

	private function sourceTerms( string $taxonomy ): array {
		global $wpdb;

		if ( ! taxonomy_exists( $taxonomy ) ) {
			throw new \InvalidArgumentException( 'Unknown taxonomy: ' . esc_html( $taxonomy ) );
		}
		if ( isset( self::$termReadMemo[ $taxonomy ] ) ) {
			return self::$termReadMemo[ $taxonomy ];
		}
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT tt.term_taxonomy_id, tt.term_id, tt.parent, tt.description, t.name, original.language_code
				FROM {$wpdb->prefix}icl_translations original
				INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = original.element_id
				INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
				WHERE original.element_type = %s AND original.source_language_code IS NULL
				ORDER BY tt.term_taxonomy_id",
				'tax_' . $taxonomy
			),
			ARRAY_A
		);
		$ttidByTermId = [];
		foreach ( $rows ?: [] as $row ) {
			$ttidByTermId[ (int) $row['term_id'] ] = (int) $row['term_taxonomy_id'];
		}
		$source = [];
		foreach ( $rows ?: [] as $row ) {
			$source[ (int) $row['term_taxonomy_id'] ] = [
				'ttid'           => (int) $row['term_taxonomy_id'],
				'termId'         => (int) $row['term_id'],
				'parent'         => $ttidByTermId[ (int) $row['parent'] ] ?? 0,
				'name'           => (string) $row['name'],
				'description'    => (string) $row['description'],
				'sourceLanguage' => (string) $row['language_code'],
			];
		}

		return self::$termReadMemo[ $taxonomy ] = $source;
	}

	private function readTermStatuses( string $taxonomy, array $source, array $languages ): array {
		global $wpdb;

		$statuses = [];
		foreach ( $source as $id => $term ) {
			foreach ( $languages as $language ) {
				$statuses[ $id ][ $language ] = $language === $term['sourceLanguage'] ? 'complete' : 'missing';
			}
		}
		if ( ! $source || ! $languages ) {
			return $statuses;
		}
		$languageList = implode( ',', $languages );
		TermBundleMembers::ensureTable( $wpdb );
		foreach ( array_chunk( array_keys( $source ), 2000 ) as $chunk ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT original.element_id, translations.language_code, ts.status, ts.needs_update,
					COALESCE(
						(SELECT CASE WHEN bj.editor_job_id > 0 OR bts.status = %d THEN 1 ELSE 0 END
						FROM {$wpdb->prefix}icl_term_batch_members m
						INNER JOIN {$wpdb->prefix}icl_translate_job bj ON bj.job_id = m.batch_job_id
						INNER JOIN {$wpdb->prefix}icl_translation_status bts ON bts.rid = bj.rid
						WHERE m.term_rid = ts.rid AND m.state = %s
						ORDER BY m.batch_job_id DESC LIMIT 1),
						(SELECT job.editor_job_id FROM {$wpdb->prefix}icl_translate_job job
						WHERE job.rid = ts.rid ORDER BY job.job_id DESC LIMIT 1)
					) AS progressing
					FROM {$wpdb->prefix}icl_translations original
					INNER JOIN {$wpdb->prefix}icl_translations translations ON translations.trid = original.trid
					AND FIND_IN_SET(translations.language_code, %s)
					LEFT JOIN {$wpdb->prefix}icl_translation_status ts ON ts.translation_id = translations.translation_id
					WHERE original.element_type = %s AND original.source_language_code IS NULL
					AND original.element_id BETWEEN %d AND %d",
					ICL_TM_ATE_NEEDS_RETRY,
					TermBundleMembers::STATE_IN_BUNDLE,
					$languageList,
					'tax_' . $taxonomy,
					(int) $chunk[0],
					(int) $chunk[ count( $chunk ) - 1 ]
				),
				ARRAY_A
			);
			foreach ( $rows ?: [] as $row ) {
				$id      = (int) $row['element_id'];
				$lang    = (string) $row['language_code'];
				$status  = null === $row['status'] ? null : (int) $row['status'];
				$stale   = 1 === (int) $row['needs_update'];
				$current = 'missing';
				if ( ( null === $status || ICL_TM_COMPLETE === $status ) && ! $stale ) {
					$current = 'complete';
				} elseif ( ICL_TM_COMPLETE === $status && $stale ) {
					$current = \WPML\Setup\Option::shouldTranslateEverything() ? 'preparing' : 'needs_update';
				} elseif ( ICL_TM_IN_PROGRESS === $status ) {
					$current = empty( $row['progressing'] ) ? 'stalled' : 'in_progress';
				} elseif ( in_array( $status, [ 1, 4, 40, 102 ], true ) ) {
					$current = 'in_progress';
				} elseif ( \WPML\Setup\Option::shouldTranslateEverything() ) {
					$current = 'preparing';
				}
				$statuses[ $id ][ $lang ] = $current;
			}
		}

		return $statuses;
	}

	private function readCarriedPairs( string $taxonomy, array $ids, array $languages ): array {
		global $wpdb;

		$carried = [];
		if ( ! $ids || ! $languages ) {
			return $carried;
		}
		unset( $taxonomy );
		$ids = array_map( 'intval', $ids );
		foreach ( array_chunk( array_values( $ids ), 2000 ) as $chunk ) {
			$pairs = [];
			foreach ( $chunk as $id ) {
				foreach ( $languages as $language ) {
					$pairs[] = [ $id, (string) $language ];
				}
			}
			$found = \WPML\Legacy\Component\Translation\Sender\Taxonomy\CarriedTermPairs::read( $wpdb, $pairs );
			if ( \WPML\Legacy\Component\Translation\Sender\Taxonomy\CarriedTermPairs::failed() ) {
				throw new \RuntimeException( 'Could not read terms carried by post jobs.' );
			}
			foreach ( $found as $pair ) {
				$carried[ $pair['ttid'] ][ $pair['language'] ] = true;
			}
		}

		return $carried;
	}

	private function makeTermItems( string $taxonomy, array $terms, array $source, ?array $statuses = null ): array {
		$languages = $this->getSecondaryLanguages();
		$statuses  = null === $statuses ? $this->readTermStatuses( $taxonomy, $source, $languages ) : $statuses;
		$carried   = $this->readCarriedPairs( $taxonomy, array_map( 'intval', array_column( $terms, 'ttid' ) ), $languages );
		$words     = $this->wordsOfTerms( $taxonomy, $terms );
		$tree      = $this->branchSizes( $source, $statuses, $languages );
		$items     = [];
		foreach ( $terms as $term ) {
			$id            = (int) $term['ttid'];
			$termLanguages = [];
			foreach ( $languages as $language ) {
				$termLanguages[] = [
					'code'                   => $language,
					'status'                 => $statuses[ $id ][ $language ] ?? 'missing',
					'inTranslationWithPosts' => ! empty( $carried[ $id ][ $language ] ),
				];
			}
			$ancestorIds = $this->ancestorIds( $source, $id );
			$items[]     = [
				'termId'             => (int) $term['termId'],
				'ttid'               => $id,
				'parent'             => (int) $term['parent'],
				'name'               => (string) $term['name'],
				'path'               => $this->termPath( $source, $id ),
				'ancestors'          => array_map(
					function ( int $ancestorId ) use ( $source ): string {
						return (string) $source[ $ancestorId ]['name'];
					},
					$ancestorIds
				),
				'ancestorIds'        => $ancestorIds,
				'descendantCount'    => $tree['descendants'][ $id ] ?? 0,
				'missingDescendants' => $tree['missing'][ $id ] ?? 0,
				'words'              => $words[ $id ] ?? 0,
				'languages'          => $termLanguages,
			];
		}

		return $items;
	}

	public function describeTermsRootFirst( string $taxonomy, array $ids ): array {
		$source = $this->sourceTerms( $taxonomy );
		$depth  = [];
		foreach ( $ids as $id ) {
			if ( isset( $source[ (int) $id ] ) ) {
				$depth[ (int) $id ] = count( $this->ancestorIds( $source, (int) $id ) );
			}
		}
		uksort(
			$depth,
			function ( int $left, int $right ) use ( $depth ): int {
				return $depth[ $left ] <=> $depth[ $right ] ?: $left <=> $right;
			}
		);

		return array_map(
			function ( int $id ) use ( $source ): array {
				return [ 'ttid' => $id, 'name' => (string) $source[ $id ]['name'] ];
			},
			array_keys( $depth )
		);
	}

	private function ancestorIds( array $source, int $id ): array {
		$ids  = [];
		$seen = [ $id => true ];
		$id   = (int) ( $source[ $id ]['parent'] ?? 0 );
		while ( isset( $source[ $id ] ) && ! isset( $seen[ $id ] ) ) {
			$seen[ $id ] = true;
			array_unshift( $ids, $id );
			$id = (int) $source[ $id ]['parent'];
		}

		return $ids;
	}

	private function branchSizes( array $source, array $statuses, array $languages ): array {
		$children = $this->childrenOf( $source );
		$order    = [];
		$seen     = [];
		$stack    = [];
		foreach ( $source as $id => $term ) {
			if ( ! isset( $source[ (int) $term['parent'] ] ) ) {
				$stack[] = $id;
			}
		}
		while ( $stack ) {
			$id = array_pop( $stack );
			if ( isset( $seen[ $id ] ) ) {
				continue;
			}
			$seen[ $id ] = true;
			$order[]     = $id;
			foreach ( $children[ $id ] ?? [] as $child ) {
				$stack[] = $child;
			}
		}

		$descendants = [];
		$missing     = [];
		foreach ( array_reverse( $order ) as $id ) {
			$parent = (int) $source[ $id ]['parent'];
			if ( ! isset( $seen[ $parent ] ) ) {
				continue;
			}
			$incomplete = 0;
			foreach ( $languages as $language ) {
				if ( 'complete' !== ( $statuses[ $id ][ $language ] ?? 'missing' ) ) {
					$incomplete = 1;
					break;
				}
			}
			$descendants[ $parent ] = ( $descendants[ $parent ] ?? 0 ) + 1 + ( $descendants[ $id ] ?? 0 );
			$missing[ $parent ]     = ( $missing[ $parent ] ?? 0 ) + $incomplete + ( $missing[ $id ] ?? 0 );
		}

		return [
			'descendants' => $descendants,
			'missing'     => $missing,
		];
	}

	private function childrenOf( array $source ): array {
		$children = [];
		foreach ( $source as $id => $term ) {
			$children[ (int) $term['parent'] ][] = $id;
		}

		return $children;
	}

	private function wordsOfTerms( string $taxonomy, array $terms ): array {
		$keys  = TranslatableTermMeta::keys( $taxonomy );
		$words = [];
		foreach ( array_chunk( $terms, 2000, true ) as $chunk ) {
			$meta  = TranslatableTermMeta::valuesOfTermsByIds( array_map( 'intval', array_column( $chunk, 'termId' ) ), $keys, $taxonomy );
			$owned = OwnerJobService::ownedStringsOfTerms( array_map( 'intval', array_column( $chunk, 'ttid' ) ) ) ?: [];
			foreach ( $chunk as $term ) {
				$words[ (int) $term['ttid'] ] = TermWords::count(
					TermWords::fieldsOf( (string) $term['name'], (string) $term['description'], $meta[ (int) $term['termId'] ] ?? [], 0, $owned[ (int) $term['ttid'] ] ?? [] ),
					(string) ( $term['sourceLanguage'] ?? '' )
				);
			}
		}

		return $words;
	}

	private function termPath( array $source, int $id ): string {
		$names = [];
		$seen  = [];
		while ( isset( $source[ $id ] ) && ! isset( $seen[ $id ] ) ) {
			$seen[ $id ] = true;
			array_unshift( $names, $source[ $id ]['name'] );
			$id = (int) $source[ $id ]['parent'];
		}

		return implode( ' / ', $names );
	}

	private function descendantIds( array $children, int $parent ): array {
		$found = [];
		$queue = $children[ $parent ] ?? [];
		while ( $queue ) {
			$id = array_shift( $queue );
			if ( isset( $found[ $id ] ) ) {
				continue;
			}
			$found[ $id ] = true;
			$queue        = array_merge( $queue, $children[ $id ] ?? [] );
		}

		return array_keys( $found );
	}

	private function shape( array $rows, array $labelRows, int $hiddenCount, bool $isTeaActive, bool $isStActive ): array {
		return [
			'rows'                       => $rows,
			'labelRows'                  => $labelRows,
			'hiddenNotTranslatableCount' => $hiddenCount,
			'isTeaActive'                => $isTeaActive,
			'isStActive'                 => $isStActive,
			'settingsUrl'                => $this->getSettingsUrl(),
		];
	}

	private function getEditUrl( string $taxonomy ): string {
		if ( ! function_exists( 'admin_url' ) ) {
			return '';
		}

		$folder = defined( 'WPML_PLUGIN_FOLDER' ) ? WPML_PLUGIN_FOLDER : 'sitepress-multilingual-cms';

		return admin_url(
			'admin.php?page=' . $folder . '/menu/taxonomy-translation.php&taxonomy=' . rawurlencode( $taxonomy )
		);
	}

	private function getSecondaryLanguages(): array {
		return TranslationPause::filterTranslatable( Languages::getSecondaryCodes() );
	}

	private function getLanguageColumns( string $defaultLang, array $secondaryLanguages ): array {
		return array_values( array_unique( array_merge( $secondaryLanguages, [ $defaultLang ] ) ) );
	}

	private function isPublicTaxonomy( $taxonomyObject ): bool {
		return ! empty( $taxonomyObject->public );
	}

	private function isVisibleTaxonomy( $taxonomyObject ): bool {
		return ! empty( $taxonomyObject->public ) || ! empty( $taxonomyObject->show_ui );
	}

	private function getLabel( $taxonomyObject, string $slug ): string {
		if ( isset( $taxonomyObject->labels->name ) && $taxonomyObject->labels->name ) {
			$label = (string) $taxonomyObject->labels->name;

			return $this->localizeAdminString( $label, 'taxonomy general name: ' . $label );
		}

		return $slug;
	}

	private function localizeAdminString( string $label, string $stringName ): string {
		if ( '' === $label ) {
			return $label;
		}

		$language = apply_filters( 'wpml_current_language', null );
		if ( ! $language ) {
			return $label;
		}

		$translated = apply_filters( 'wpml_translate_single_string', $label, 'WordPress', $stringName, $language );

		return is_string( $translated ) && '' !== $translated ? $translated : $label;
	}

	private function getPostTypeLabels( $taxonomyObject ): array {
		global $wp_post_types;

		$labels = [];
		$types  = isset( $taxonomyObject->object_type ) && is_array( $taxonomyObject->object_type )
			? $taxonomyObject->object_type
			: [];

		foreach ( $types as $type ) {
			if ( isset( $wp_post_types[ $type ]->labels->name ) ) {
				$name     = (string) $wp_post_types[ $type ]->labels->name;
				$labels[] = $this->localizeAdminString( $name, 'post type general name: ' . $name );
			} else {
				$labels[] = $type;
			}
		}

		return $labels;
	}

	private function getSourceTermRows( $wpdb, string $taxonomy ): array {
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT original_element.element_id,
					original_element.language_code AS source_lang,
					t.term_id,
					t.name,
					COALESCE( tt.description, '' ) AS description
				FROM {$wpdb->prefix}icl_translations original_element
				INNER JOIN {$wpdb->prefix}term_taxonomy tt
					ON tt.term_taxonomy_id = original_element.element_id
				INNER JOIN {$wpdb->prefix}terms t
					ON t.term_id = tt.term_id
				WHERE original_element.element_type = %s
				AND original_element.source_language_code IS NULL",
				'tax_' . $taxonomy
			),
			ARRAY_A
		);

		return $rows ?: [];
	}

	const STATUS_MAP_CHUNK = 250;

	private function getTranslationStatusMap( $wpdb, string $taxonomy, array $languages, array $elementIds ): array {
		if ( empty( $languages ) || empty( $elementIds ) ) {
			return [];
		}

		sort( $elementIds, SORT_NUMERIC );
		$placeholders = implode( ',', array_fill( 0, count( $languages ), '%s' ) );
		TermBundleMembers::ensureTable( $wpdb );

		$sql = "SELECT original_element.element_id,
				translations.language_code,
				ts.status,
				ts.needs_update,
				COALESCE(
					(
						SELECT CASE WHEN bj.editor_job_id > 0 OR bts.status = %d THEN 1 ELSE 0 END
						FROM {$wpdb->prefix}icl_term_batch_members m
						INNER JOIN {$wpdb->prefix}icl_translate_job bj ON bj.job_id = m.batch_job_id
						INNER JOIN {$wpdb->prefix}icl_translation_status bts ON bts.rid = bj.rid
						WHERE m.term_rid = ts.rid AND m.state = %s
						ORDER BY m.batch_job_id DESC
						LIMIT 1
					),
					(
						SELECT job.editor_job_id
						FROM {$wpdb->prefix}icl_translate_job job
						WHERE job.rid = ts.rid
						ORDER BY job.job_id DESC
						LIMIT 1
					)
				) AS progressing
			FROM {$wpdb->prefix}icl_translations original_element
			INNER JOIN {$wpdb->prefix}icl_translations translations
				ON translations.trid = original_element.trid
				AND translations.language_code IN ( {$placeholders} )
			LEFT JOIN {$wpdb->prefix}icl_translation_status ts
				ON ts.translation_id = translations.translation_id
			WHERE original_element.element_type = %s
			AND original_element.source_language_code IS NULL
			AND original_element.element_id BETWEEN %d AND %d";

		$map = [];
		foreach ( array_chunk( $elementIds, self::STATUS_MAP_CHUNK ) as $chunk ) {
			$params = array_merge( [ ICL_TM_ATE_NEEDS_RETRY, TermBundleMembers::STATE_IN_BUNDLE ], $languages, [ 'tax_' . $taxonomy, (int) $chunk[0], (int) $chunk[ count( $chunk ) - 1 ] ] );
			$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_N );

			foreach ( $rows ?: [] as $row ) {
				$map[ (string) $row[1] ][ (int) $row[0] ] = self::statusCode( $row[2], $row[3], $row[4] );
			}

			$rows = null;
			$wpdb->flush();
		}

		return $map;
	}

	private static function statusCode( $status, $needsUpdate, $progressing ): int {
		return ( null === $status ? 0 : ( (int) $status + 1 ) << 2 )
			| ( 1 === (int) $needsUpdate ? 2 : 0 )
			| ( empty( $progressing ) ? 0 : 1 );
	}

	private function buildTaxonomyMetrics(
		$wpdb,
		string $taxonomy,
		string $defaultLang,
		array $secondaryLanguages,
		bool $isTeaActive = false
	): array {
		$sourceRows = $this->getSourceTermRows( $wpdb, $taxonomy );
		$languages  = $this->getLanguageColumns( $defaultLang, $secondaryLanguages );
		$statusMap  = $this->getTranslationStatusMap(
			$wpdb,
			$taxonomy,
			$languages,
			array_map( 'intval', array_column( $sourceRows, 'element_id' ) )
		);
		$total      = count( $sourceRows );

		$metaKeys = TranslatableTermMeta::keys( $taxonomy );

		$stalePairs = $this->stalePairs( $sourceRows, $statusMap );
		$staleIds   = [];
		foreach ( $stalePairs as $byLanguage ) {
			$staleIds += array_fill_keys( $byLanguage, true );
		}

		$staleTerms = [];
		$termWords  = [];
		foreach ( array_chunk( $sourceRows, TranslatableTermMeta::BATCH_CHUNK ) as $chunkRows ) {
			$metaValuesOfTerms = TranslatableTermMeta::valuesOfTermsByIds(
				array_map( 'intval', array_column( $chunkRows, 'term_id' ) ),
				$metaKeys,
				$taxonomy
			);
			$ownedStringsOfTerms = OwnerJobService::ownedStringsOfTerms( array_map( 'intval', array_column( $chunkRows, 'element_id' ) ) ) ?: [];
			foreach ( $chunkRows as $row ) {
				$termId     = (int) ( $row['term_id'] ?? 0 );
				$metaValues = $metaValuesOfTerms[ $termId ] ?? [];
				$elementId  = (int) $row['element_id'];
				$term       = $this->termToCount( (string) ( $row['name'] ?? '' ), (string) ( $row['description'] ?? '' ), $metaValues, $ownedStringsOfTerms[ $elementId ] ?? [], (string) ( $row['source_lang'] ?? '' ) );

				$termWords[ $elementId ] = TermWords::count( $term['fields'], $term['lang'] );
				if ( isset( $staleIds[ $elementId ] ) ) {
					$staleTerms[ $elementId ] = $term;
				}
			}
			$metaValuesOfTerms   = null;
			$ownedStringsOfTerms = null;
		}

		$staleWords = $this->changedWordsOfStalePairs( $wpdb, $taxonomy, $stalePairs, $staleTerms );

		$states = [];

		foreach ( $languages as $lang ) {
			$langMap     = isset( $statusMap[ $lang ] ) ? $statusMap[ $lang ] : [];
			$translated  = 0;
			$inProgress  = 0;
			$stalled     = 0;
			$needsUpdate = 0;
			$words       = 0;
			$langTotal = 0;
			$wordsAll  = 0;

			foreach ( $sourceRows as $row ) {
				if ( (string) ( $row['source_lang'] ?? $defaultLang ) === $lang ) {
					continue;
				}

				$elementId = (int) $row['element_id'];
				$wordsAll += $termWords[ $elementId ];
				++$langTotal;

				$code      = isset( $langMap[ $elementId ] ) ? $langMap[ $elementId ] : null;
				$hasRow    = null !== $code;
				$rowStatus = ( $hasRow && $code >= 4 ) ? ( $code >> 2 ) - 1 : null;
				$stale     = $hasRow && 2 === ( $code & 2 );

				if ( $hasRow && ( null === $rowStatus || ICL_TM_COMPLETE === $rowStatus ) && ! $stale ) {
					++$translated;
					continue;
				}

				if ( ICL_TM_IN_PROGRESS === $rowStatus ) {
					++$inProgress;
					if ( 0 === ( $code & 1 ) ) {
						++$stalled;
					}
				}

				if ( ICL_TM_COMPLETE === $rowStatus && $stale ) {
					++$needsUpdate;
					$words += isset( $staleWords[ $lang ][ $elementId ] ) ? $staleWords[ $lang ][ $elementId ] : 0;
				} else {
					$words += $termWords[ $elementId ];
				}
			}

			$untranslated = max( $langTotal - $translated, 0 );

			if ( 0 === $langTotal ) {
				$status = 'complete';
			} elseif ( $translated === $langTotal ) {
				$status = 'complete';
			} elseif ( $inProgress > 0 ) {
				$status = $stalled === $inProgress ? 'stalled' : 'in_progress';
			} elseif ( $needsUpdate > 0 ) {
				$status = $isTeaActive ? 'preparing' : 'needs_update';
			} elseif ( 0 === $translated ) {
				$status = 'missing';
			} else {
				$status = 'partial';
			}

			$states[] = [
				'code'         => $lang,
				'status'       => $status,
				'translated'   => $translated,
				'total'        => $langTotal,
				'untranslated' => $untranslated,
				'inProgress'   => $inProgress,
				'stalled'      => $stalled,
				'words'        => $words,
				'wordsAll'     => $wordsAll,
			];
		}

		return [
			'states' => $states,
			'total'  => $total,
			'words'  => (int) array_sum( $termWords ),
		];
	}

	private function stalePairs( array $sourceRows, array $statusMap ): array {
		$stalePairs = [];
		foreach ( $sourceRows as $row ) {
			$elementId  = (int) $row['element_id'];
			$sourceLang = (string) ( $row['source_lang'] ?? '' );
			foreach ( $statusMap as $lang => $langMap ) {
				$lang = (string) $lang;
				if ( $lang === $sourceLang || ! isset( $langMap[ $elementId ] ) ) {
					continue;
				}
				$code = $langMap[ $elementId ];
				if ( $code >= 4 && ICL_TM_COMPLETE === ( $code >> 2 ) - 1 && 2 === ( $code & 2 ) ) {
					$stalePairs[ (int) $row['term_id'] ][ $lang ] = $elementId;
				}
			}
		}

		return $stalePairs;
	}

	private function changedWordsOfStalePairs( $wpdb, string $taxonomy, array $stalePairs, array $terms ): array {
		if ( ! $stalePairs ) {
			return [];
		}

		$words     = [];
		$wholeTerm = [];
		$prefixes  = [ TermWords::DELIVERED_FIELDS_META_PREFIX => 'delivered', self::WC_SNAPSHOT_META_PREFIX => 'copy' ];

		foreach ( array_chunk( array_keys( $stalePairs ), self::SNAPSHOT_CHUNK ) as $termIds ) {
			$keys = [];
			foreach ( $termIds as $termId ) {
				foreach ( array_keys( $stalePairs[ $termId ] ) as $lang ) {
					foreach ( array_keys( $prefixes ) as $prefix ) {
						$keys[ $prefix . $lang ] = true;
					}
				}
			}
			$keys = array_keys( $keys );

			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT term_id, meta_key, meta_value
					FROM {$wpdb->prefix}termmeta
					WHERE term_id IN ( " . implode( ',', array_fill( 0, count( $termIds ), '%d' ) ) . ' )
					AND meta_key IN ( ' . implode( ',', array_fill( 0, count( $keys ), '%s' ) ) . ' )
					ORDER BY meta_id ASC',
					array_merge( $termIds, $keys )
				),
				ARRAY_A
			);

			$records = [];
			foreach ( $rows ?: [] as $record ) {
				$termId = (int) $record['term_id'];
				foreach ( $prefixes as $prefix => $kind ) {
					if ( 0 === strpos( (string) $record['meta_key'], $prefix ) ) {
						$records[ $termId ][ substr( (string) $record['meta_key'], strlen( $prefix ) ) ][ $kind ] = (string) $record['meta_value'];
						break;
					}
				}
			}
			$rows = null;
			$wpdb->flush();

			foreach ( $termIds as $termId ) {
				foreach ( $stalePairs[ $termId ] as $lang => $elementId ) {
					$term = $terms[ $elementId ] ?? null;
					if ( ! $term ) {
						continue;
					}
					$baseline = TermWords::decodeBaseline( $records[ $termId ][ $lang ]['delivered'] ?? '', $records[ $termId ][ $lang ]['copy'] ?? '' );
					if ( '' === $baseline ) {
						if ( ! isset( $wholeTerm[ $elementId ] ) ) {
							$wholeTerm[ $elementId ] = TermWords::count( $term['fields'], $term['lang'] );
						}
						$words[ $lang ][ $elementId ] = $wholeTerm[ $elementId ];
						continue;
					}
					$words[ $lang ][ $elementId ] = TermWords::countChanged( $elementId, $taxonomy, $term['fields'], $term['text'], $baseline, $term['lang'] );
				}
			}
		}

		return $words;
	}

	private function getSettingsUrl(): string {
		if ( ! function_exists( 'admin_url' ) ) {
			return '';
		}

		return admin_url( 'admin.php?page=tm/menu/settings&section=taxonomies' );
	}
}
