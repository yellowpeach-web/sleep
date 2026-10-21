<?php

namespace WPML\TM\Dashboard\Taxonomy;

use WPML\Element\API\Languages;
use WPML\LanguageEditor\TranslationPause;
use WPML\TM\ATE\Review\ReviewStatus;
use WPML\TM\Taxonomy\TranslatableTermMeta;
use WPML\Core\Component\WordsToTranslate\Domain\Calculator\Diff;
use WPML\Core\Component\WordsToTranslate\Domain\Calculator\Count;
use function WPML\Container\make;

class TaxonomyDashboardData {

	const WC_SNAPSHOT_META_PREFIX = '_wpml_tax_wc_src_';

	private $diffCalculator = null;

	private $countCalculator = null;

	private static $requestMemo = [];

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
		$memoKey    = md5(
			(string) json_encode(
				[
					$search,
					$stateToken,
					function_exists( 'wp_cache_get_last_changed' ) ? wp_cache_get_last_changed( 'terms' ) : '',
					$taxonomies,
					$translated,
					$termKeySettings,
					$this->getSecondaryLanguages(),
					Languages::getDefaultCode(),
					apply_filters( 'wpml_current_language', null ),
				]
			)
		);
		if ( isset( self::$requestMemo[ $memoKey ] ) ) {
			return self::$requestMemo[ $memoKey ];
		}

		$data = $this->build( $search );

		self::$requestMemo = [ $memoKey => $data ];

		return $data;
	}

	public static function forgetRequestMemo() {
		self::$requestMemo = [];
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

	private function build( string $search ): array {
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

			$metrics        = $this->buildTaxonomyMetrics(
				$wpdb,
				$slug,
				$defaultLanguageCode,
				$secondaryLanguages,
				$isTeaActive
			);
			$languageStates = $metrics['states'];

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
			AND t.name LIKE %s
			ORDER BY t.name
			LIMIT 100";

		$params = array_merge( $taxonomies, [ '%' . $wpdb->esc_like( $search ) . '%' ] );
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
			return [ 'pairs' => [], 'lastElementId' => $afterElementId, 'hasMore' => false ];
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

		$sql = "SELECT original_element.element_id,
				translations.language_code,
				ts.status,
				ts.needs_update,
				(
					SELECT job.editor_job_id
					FROM {$wpdb->prefix}icl_translate_job job
					WHERE job.rid = ts.rid
					ORDER BY job.job_id DESC
					LIMIT 1
				) AS editor_job_id
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
			$params = array_merge( $languages, [ 'tax_' . $taxonomy, (int) $chunk[0], (int) $chunk[ count( $chunk ) - 1 ] ] );
			$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_N );

			foreach ( $rows ?: [] as $row ) {
				$map[ (string) $row[1] ][ (int) $row[0] ] = self::statusCode( $row[2], $row[3], $row[4] );
			}

			$rows = null;
			$wpdb->flush();
		}

		return $map;
	}

	private static function statusCode( $status, $needsUpdate, $editorJobId ): int {
		return ( null === $status ? 0 : ( (int) $status + 1 ) << 2 )
			| ( 1 === (int) $needsUpdate ? 2 : 0 )
			| ( empty( $editorJobId ) ? 0 : 1 );
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

		$termTexts = [];
		$termWords = [];
		foreach ( array_chunk( $sourceRows, TranslatableTermMeta::BATCH_CHUNK ) as $chunkRows ) {
			$metaValuesOfTerms = TranslatableTermMeta::valuesOfTermsByIds(
				array_map( 'intval', array_column( $chunkRows, 'term_id' ) ),
				$metaKeys,
				$taxonomy
			);
			foreach ( $chunkRows as $row ) {
				$termId             = (int) ( $row['term_id'] ?? 0 );
				$nameAndDescription = (string) ( $row['name'] ?? '' ) . "\n" . (string) ( $row['description'] ?? '' );
				$metaValues         = $metaValuesOfTerms[ $termId ] ?? [];

				$elementId               = (int) $row['element_id'];
				$termTexts[ $elementId ] = $this->withMetaText( $nameAndDescription, implode( "\n", $metaValues ) );
				$termWords[ $elementId ] = $this->wordCount( $this->withMetaText( $nameAndDescription, TranslatableTermMeta::countableText( $metaValues ) ) );
			}
			$metaValuesOfTerms = null;
		}

		$staleWords = $this->countChangedWordsOfStalePairs( $wpdb, $sourceRows, $statusMap, $termTexts );

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
		];
	}

	private function countChangedWordsOfStalePairs( $wpdb, array $sourceRows, array $statusMap, array $termTexts ): array {
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

		if ( ! $stalePairs ) {
			return [];
		}

		$words      = [];
		$wholeTerm  = [];
		$prefixSize = strlen( self::WC_SNAPSHOT_META_PREFIX );

		foreach ( array_chunk( array_keys( $stalePairs ), self::SNAPSHOT_CHUNK ) as $termIds ) {
			$keys = [];
			foreach ( $termIds as $termId ) {
				foreach ( array_keys( $stalePairs[ $termId ] ) as $lang ) {
					$keys[ self::WC_SNAPSHOT_META_PREFIX . $lang ] = true;
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

			foreach ( $rows ?: [] as $snapshot ) {
				$termId = (int) $snapshot['term_id'];
				$lang   = substr( (string) $snapshot['meta_key'], $prefixSize );
				if ( ! isset( $stalePairs[ $termId ][ $lang ] ) ) {
					continue;
				}
				$elementId = $stalePairs[ $termId ][ $lang ];
				$baseline  = function_exists( 'maybe_unserialize' ) ? maybe_unserialize( $snapshot['meta_value'] ) : $snapshot['meta_value'];

				$words[ $lang ][ $elementId ] = $this->countChangedWords(
					is_scalar( $baseline ) ? (string) $baseline : '',
					$termTexts[ $elementId ]
				);
				unset( $stalePairs[ $termId ][ $lang ] );
			}

			$rows = null;
			$wpdb->flush();

			foreach ( $termIds as $termId ) {
				foreach ( $stalePairs[ $termId ] as $lang => $elementId ) {
					if ( ! isset( $wholeTerm[ $elementId ] ) ) {
						$wholeTerm[ $elementId ] = $this->countChangedWords( '', $termTexts[ $elementId ] );
					}
					$words[ $lang ][ $elementId ] = $wholeTerm[ $elementId ];
				}
			}
		}

		return $words;
	}

	private function countChangedWords( string $previous, string $current ): int {
		$current = trim( wp_strip_all_tags( $current ) );
		if ( '' === $current ) {
			return 0;
		}

		$previous = trim( wp_strip_all_tags( $previous ) );
		if ( '' === $previous ) {
			return $this->wordCount( $current );
		}

		return (int) $this->countCalculator()->wordsToTranslate(
			$this->diffCalculator()->diffStrings( $previous, $current )
		);
	}

	private function diffCalculator(): Diff {
		if ( null === $this->diffCalculator ) {
			$this->diffCalculator = make( Diff::class );
		}

		return $this->diffCalculator;
	}

	private function countCalculator(): Count {
		if ( null === $this->countCalculator ) {
			$this->countCalculator = make( Count::class );
		}

		return $this->countCalculator;
	}

	private function withMetaText( string $nameAndDescription, string $metaText ): string {
		return '' === $metaText ? $nameAndDescription : $nameAndDescription . "\n" . $metaText;
	}

	private function wordCount( string $text ): int {
		$clean = trim( wp_strip_all_tags( $text ) );
		if ( '' === $clean ) {
			return 0;
		}

		return count( preg_split( '/\s+/u', $clean ) ?: [] );
	}

	private function getSettingsUrl(): string {
		if ( ! function_exists( 'admin_url' ) ) {
			return '';
		}

		return admin_url( 'admin.php?page=tm/menu/settings&section=taxonomies' );
	}
}
