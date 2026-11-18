<?php

namespace WPML\TM\ATE\TranslateEverything;

use WPML\Element\API\Languages;
use WPML\Core\Component\Translation\Application\Service\Taxonomy\TaxonomyTermPairClaimRepositoryInterface;
use WPML\Legacy\Component\Translation\Sender\Taxonomy\CarriedTermPairs;
use WPML\Legacy\Component\Translation\Sender\Taxonomy\TaxonomyTermPairClaimRepository;
use WPML\FP\Cast;
use WPML\FP\Fns;
use WPML\FP\Lst;
use WPML\FP\Obj;
use WPML\LanguageEditor\TranslationPause;
use WPML\Setup\Option;
use WPML\TM\API\ATE\CachedLanguageMappings;
use WPML\TM\API\ATE\LanguageMappings;
use WPML\TM\AutomaticTranslation\Actions\Actions;
use WPML\TM\Jobs\JobLog;
use function WPML\Container\make;
use function WPML\FP\pipe;

class UntranslatedTerms extends AbstractUntranslatedElements {

	const ELEMENT_TYPE_PREFIX = 'tax_';

	protected function getElementTypePrefix(): string {
		return self::ELEMENT_TYPE_PREFIX;
	}

	const TYPE_SEPARATOR = ',';

	public function getQueueSize(): int {
		return TermBundle::MAX_TERMS;
	}

	private static function taxonomiesOf( string $type ): array {
		return array_values( array_filter( explode( self::TYPE_SEPARATOR, $type ) ) );
	}

	public function getEligibleLanguageCodes( bool $cached = false ): array {
		$codes  = parent::getEligibleLanguageCodes( $cached );
		$mapper = $cached ? CachedLanguageMappings::class : LanguageMappings::class;

		foreach ( $this->getSecondaryOriginLanguages() as $originLang ) {
			$codes = array_merge( $codes, $mapper::geCodesEligibleForAutomaticTranslations( $originLang ) );
		}

		return TranslationPause::filterTranslatable( array_values( array_unique( $codes ) ) );
	}

	private function getSecondaryOriginLanguages(): array {
		$elementTypes = array_map(
			function ( $taxonomy ) {
				return self::ELEMENT_TYPE_PREFIX . $taxonomy;
			},
			$this->getTypes()
		);

		if ( empty( $elementTypes ) ) {
			return [];
		}

		$wpdb         = $this->wpdb;
		$placeholders = Lst::join( ',', array_fill( 0, count( $elementTypes ), '%s' ) );

		$sql = "SELECT DISTINCT language_code FROM {$wpdb->prefix}icl_translations
			WHERE element_type IN ( {$placeholders} )
			AND source_language_code IS NULL
			AND language_code != %s";

		$codes = $wpdb->get_col(
			$wpdb->prepare(
				$sql,
				array_merge( $elementTypes, [ Languages::getDefaultCode() ] )
			)
		);

		return array_map( 'strval', $codes ?: [] );
	}

	protected function getCompletableLanguageCodes(): array {
		return array_values( array_unique( array_keys( Languages::getActive() ) ) );
	}

	public function getTypeWithLanguagesToProcess() {
		$taxonomies = array_filter(
			$this->getTypes(),
			function ( string $taxonomy ): bool {
				return self::COMPLETION_REFUSAL_CARRIED_PAIR !== $this->getCompletionRefusalReason( $taxonomy );
			}
		);
		$eligible   = $this->getEligibleLanguageCodes( true );
		$taxonomies = $this->getTaxonomiesToTranslate( $taxonomies, $eligible );
		if ( ! $taxonomies ) {
			return null;
		}
		$open = array_merge( ...array_column( $taxonomies, 1 ) );

		return [
			implode( self::TYPE_SEPARATOR, array_column( $taxonomies, 0 ) ),
			array_values( array_intersect( $eligible, $open ) ),
		];
	}

	public function markTypeAsCompleted( string $type, ?array $probeLanguages = null, int $probeLimit = 1 ) {
		$completedNow = true;
		foreach ( self::taxonomiesOf( $type ) as $taxonomy ) {
			$completedNow = parent::markTypeAsCompleted( $taxonomy, $probeLanguages, $probeLimit ) && $completedNow;
		}

		return $completedNow;
	}

	private function getTaxonomiesToTranslate( array $taxonomies, array $targetLanguages ) {
		$completed                           = $this->getCompleted();
		$getLanguageCodesNotCompletedForType = pipe( Obj::propOr( [], Fns::__, $completed ), Lst::diff( $targetLanguages ) );

		$mapTaxonomy = function ( $taxonomy ) use ( $getLanguageCodesNotCompletedForType ) {
			return [ $taxonomy, $getLanguageCodesNotCompletedForType( $taxonomy ) ];
		};

		$pipeTaxonomies = pipe(
			Fns::map( $mapTaxonomy ),
			Fns::filter( pipe( Obj::prop( 1 ), Lst::length() ) )
		);

		return $pipeTaxonomies( $taxonomies );
	}

	public function getElementsToProcess( $languages, $type, $queueSize ) {
		return $this->getElementsToProcessInternal( $languages, $type, $queueSize, false );
	}

	private function getElementsToProcessInternal( $languages, $type, $queueSize, $carriedOnly ) {
		if ( empty( $languages ) ) {
			return [];
		}

		$languages_part   = $this->buildLanguagesUnion( $languages );
		$pair_not_refused = $this->pairNotRefusedSql( 'original_element.language_code', $languages );
		$element_types    = array_map(
			function ( string $taxonomy ): string {
				return self::ELEMENT_TYPE_PREFIX . $taxonomy;
			},
			self::taxonomiesOf( (string) $type )
		);
		$type_placeholders = Lst::join( ',', array_fill( 0, count( $element_types ), '%s' ) );
		$discarded_job       = $this->toSend()->joinSql( 'translation_status' );
		$not_a_discarded_job = $this->toSend()->notDiscardedSql( 'translation_status' );
		$not_carried         = $carriedOnly
			? 'AND EXISTS ( SELECT STRAIGHT_JOIN 1 ' . CarriedTermPairs::sql( $this->wpdb ) . '
				WHERE relationships.term_taxonomy_id = original_element.element_id
				AND unit.field_type = CONCAT(\'t_\', original_element.element_id)
				AND target.language_code = languages.code )'
			: 'AND NOT EXISTS ( SELECT STRAIGHT_JOIN 1 ' . CarriedTermPairs::sql( $this->wpdb ) . '
				WHERE relationships.term_taxonomy_id = original_element.element_id
				AND unit.field_type = CONCAT(\'t_\', original_element.element_id)
				AND target.language_code = languages.code )';
		$refused_name        = RefusedTermName::joinSql( $this->wpdb->prefix, 'original_element', 'languages' );
		$not_a_refused_name  = RefusedTermName::whereSql();

		$sql = "
			SELECT original_element.element_id, languages.code, original_element.element_type
			FROM {$this->wpdb->prefix}icl_translations original_element
			INNER JOIN ( {$languages_part} ) as languages
			LEFT JOIN {$this->wpdb->prefix}icl_translations translations
				ON translations.trid = original_element.trid
				AND translations.language_code = languages.code
			LEFT JOIN {$this->wpdb->prefix}icl_translation_status translation_status
				ON translation_status.translation_id = translations.translation_id
			{$discarded_job}
			{$refused_name}

			WHERE original_element.element_type IN ( {$type_placeholders} )
				AND original_element.source_language_code IS NULL
				-- No `AND original_element.language_code = <default>` here, on
				-- purpose (wpmldev-8068). `source_language_code IS NULL` already
				-- identifies the ORIGINAL of a translation group, whatever
				-- language it was authored in - which is exactly how
				-- UntranslatedPosts keys its inventory. Pinning the original to
				-- the site default made every term authored directly in a
				-- secondary language invisible to this sweep: never queued,
				-- never dispatched, never counted. The `languages.code <>
				-- original_element.language_code` line below is what keeps a
				-- term from being queued into its own language, and it works for
				-- any origin language.
				AND languages.code <> original_element.language_code
				{$pair_not_refused}
					{$not_carried}
				-- WPML's built-in default-term translations have a translation row
				-- but no status row: the shared rule applies only when status exists.
				AND (
					translations.translation_id IS NULL
					OR (
						translation_status.translation_id IS NOT NULL
						AND {$this->toSend()->sql( 'translation_status' )}
					)
				)
				{$not_a_discarded_job}
				{$not_a_refused_name}
			-- Target language first, so one tick's window fills a bundle per
			-- language across the pooled taxonomies; roots before children (the
			-- refused-name join already has the term row), so a bundle's parents
			-- are in the same window as their children (wpmldev-12374).
			ORDER BY languages.code, refused_name_tt.parent <> 0, original_element.element_id
			LIMIT %d
		";

		$result = $this->wpdb->get_results(
			$this->wpdb->prepare(
				$sql,
				...array_merge( $element_types, [ $this->toSend()->joblessCutoff(), $queueSize ] )
			),
			ARRAY_N
		);
		if ( '' !== $this->wpdb->last_error ) {
			throw new StrategyReadFailed( esc_html( $this->wpdb->last_error ) );
		}

		$prefixLength = strlen( self::ELEMENT_TYPE_PREFIX );

		return Fns::map(
			Obj::evolve(
				[
					0 => Cast::toInt(),
					2 => function ( $elementType ) use ( $prefixLength ) {
						return substr( (string) $elementType, $prefixLength );
					},
				]
			),
			$result
		);
	}

	protected function hasCarriedPairToProcessNow( array $languages, string $type ): bool {
		return (bool) $this->getElementsToProcessInternal( $languages, $type, 1, true );
	}

	private function getOriginalLanguage( int $termTaxonomyId, string $elementType ): string {
		$wpdb = $this->wpdb;

		$lang = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT language_code FROM {$wpdb->prefix}icl_translations
				WHERE element_id = %d AND element_type = %s AND source_language_code IS NULL",
				$termTaxonomyId,
				$elementType
			)
		);

		return $lang ? (string) $lang : Languages::getDefaultCode();
	}


	public function createTranslationJobs( Actions $actions, array $elements, $type ) {
		CarriedTermPairs::clear();
		try {
			return $this->createTranslationJobsWithCarriedCheck( $actions, $elements, $type );
		} finally {
			CarriedTermPairs::clear();
		}
	}

	private function createTranslationJobsWithCarriedCheck( Actions $actions, array $elements, $type ) {
		unset( $actions );

		$taxonomies   = self::taxonomiesOf( (string) $type );
		$carriedPairs = CarriedTermPairs::find( $this->wpdb, $elements );
		if ( CarriedTermPairs::failed() ) {
			$this->markTaxonomiesAsUncompleted( $taxonomies );

			return [];
		}
		$dispatcher  = make( TaxonomyTermJobDispatcher::class, [ ':createdByTranslateEverything' => true ] );

		$members     = [];
		$refused     = [];
		$seenPairs   = [];
		$claims      = [];
		$claimOwner  = 'tea-' . bin2hex( random_bytes( 16 ) );
		$claimRepository = $this->termPairClaims();

		foreach ( $elements as $element ) {
			$termTaxonomyId = (int) $element[0];
			$targetLang     = (string) $element[1];
			$taxonomy       = (string) ( $element[2] ?? $type );
			$elementType    = self::ELEMENT_TYPE_PREFIX . $taxonomy;

			$pairKey = $termTaxonomyId . '|' . $targetLang;
			if ( isset( $seenPairs[ $pairKey ] ) ) {
				continue;
			}
			$seenPairs[ $pairKey ] = true;
			if ( isset( $carriedPairs[ $pairKey ] ) ) {
				continue;
			}

			$sourceLang = $this->getOriginalLanguage( $termTaxonomyId, $elementType );

			if ( $targetLang === $sourceLang ) {
				continue;
			}

			if ( ! $claimRepository->claim( $termTaxonomyId, $targetLang, $claimOwner ) ) {
				continue;
			}
			$claims[] = [ $termTaxonomyId, $targetLang ];

			$member = $dispatcher->prepareTermBundleMember( $termTaxonomyId, $taxonomy, $sourceLang, $targetLang, true );
			if ( $member ) {
				$members[] = $member;
			} else {
				$claimRepository->release( $termTaxonomyId, $targetLang, $claimOwner );
				$refused[ $dispatcher->getLastRefusal() ?: 'not_prepared' ][] = $termTaxonomyId;
			}
		}

		if ( $refused ) {
			JobLog::add(
				'tea_term_members_refused',
				[
					'taxonomies' => $taxonomies,
					'refused'    => array_sum( array_map( 'count', $refused ) ),
					'reasons'      => array_map( function ( array $termTaxonomyIds ) {
						return [ 'count' => count( $termTaxonomyIds ), 'term_taxonomy_ids' => array_slice( $termTaxonomyIds, 0, 5 ) ];
					}, $refused ),
				]
			);
		}

		$descriptors = [];
		if ( $members ) {
			$bundles        = $dispatcher->buildTermBundles( $members );
			$bundlesByJobId = [];
			$models         = [];
			$bundled        = 0;
			foreach ( $bundles as $bundle ) {
				$bundlesByJobId[ $bundle->getJobId() ] = $bundle;
				$models[]                              = $bundle->getModel();
				$bundled                              += count( $bundle->getMembers() );
			}
			if ( $bundled < count( $members ) ) {
				JobLog::addError( 'tea_term_bundle_not_built', [ 'taxonomies' => $taxonomies, 'members' => count( $members ), 'bundled' => $bundled ] );
				$this->markTaxonomiesAsUncompleted( $taxonomies );
			}

			$dispatchResult  = $dispatcher->dispatchToAte( $models );
			$retryableJobIds = array_merge(
				$dispatchResult->getRejectedJobIds(),
				$dispatchResult->getRemainingJobIds()
			);
			$rejected        = array_values(
				array_filter(
					array_map(
						function ( $jobId ) use ( $bundlesByJobId ) {
							return $bundlesByJobId[ $jobId ] ?? null;
						},
						$retryableJobIds
					)
				)
			);

			$dispatcher->releasePreparedBundles( $rejected );

			if ( $rejected ) {
				$this->markTaxonomiesAsUncompleted( $taxonomies );
			}

			$durableJobIds = array_flip(
				array_merge(
					$dispatchResult->getAcceptedJobIds(),
					$dispatchResult->getBlockedJobIds(),
					$dispatchResult->getParkedJobIds()
				)
			);
			foreach ( $bundles as $bundle ) {
				if ( isset( $durableJobIds[ $bundle->getJobId() ] ) ) {
					$descriptors[] = [
						'elementId'       => $bundle->getBatchId(),
						'lang'            => $bundle->getTargetLanguage(),
						'elementType'     => TermBundle::ELEMENT_TYPE,
						'jobId'           => $bundle->getJobId(),
						'termTaxonomyIds' => $bundle->getTermTaxonomyIds(),
					];
				}
			}
		}

		foreach ( $claims as $claim ) {
			$claimRepository->release( (int) $claim[0], (string) $claim[1], $claimOwner );
		}

		return $descriptors;
	}

	protected function termPairClaims() {
		return new TaxonomyTermPairClaimRepository( $this->wpdb );
	}

	protected function getCompleted(): array {
		return Option::getTranslateEverythingCompletedTaxonomies();
	}

	protected function getCompletedOptionKey() {
		return Option::TRANSLATE_EVERYTHING_TAXONOMIES_COMPLETED;
	}

	protected function getTypes(): array {
		return TranslatableTaxonomies::getEligibleForTea();
	}

	public function markTaxonomyAsUncompleted( string $taxonomy ) {
		$this->markTaxonomiesAsUncompleted( [ $taxonomy ] );
	}

	private function markTaxonomiesAsUncompleted( array $taxonomies ) {
		$this->updateCompleted(
			function ( array $completed ) use ( $taxonomies ) {
				foreach ( $taxonomies as $taxonomy ) {
					$completed[ $taxonomy ] = [];
				}

				return $completed;
			}
		);
	}
}
