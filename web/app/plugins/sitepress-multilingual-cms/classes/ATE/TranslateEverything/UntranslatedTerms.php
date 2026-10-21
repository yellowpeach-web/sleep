<?php

namespace WPML\TM\ATE\TranslateEverything;

use WPML\Element\API\Languages;
use WPML\Core\Component\Translation\Application\Service\Taxonomy\TaxonomyTermPairClaimRepositoryInterface;
use WPML\Legacy\Component\Translation\Sender\Taxonomy\TaxonomyTermPairClaimRepository;
use WPML\FP\Cast;
use WPML\FP\Fns;
use WPML\FP\Lst;
use WPML\FP\Obj;
use WPML\Setup\Option;
use WPML\TM\API\ATE\CachedLanguageMappings;
use WPML\TM\API\ATE\LanguageMappings;
use WPML\TM\AutomaticTranslation\Actions\Actions;
use function WPML\Container\make;
use function WPML\FP\pipe;

class UntranslatedTerms extends AbstractUntranslatedElements {

	const ELEMENT_TYPE_PREFIX = 'tax_';

	protected function getElementTypePrefix(): string {
		return self::ELEMENT_TYPE_PREFIX;
	}

	public function getEligibleLanguageCodes( bool $cached = false ): array {
		$codes  = parent::getEligibleLanguageCodes( $cached );
		$mapper = $cached ? CachedLanguageMappings::class : LanguageMappings::class;

		foreach ( $this->getSecondaryOriginLanguages() as $originLang ) {
			$codes = array_merge( $codes, $mapper::geCodesEligibleForAutomaticTranslations( $originLang ) );
		}

		return array_values( array_unique( $codes ) );
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
		$taxonomies = $this->getTaxonomiesToTranslate(
			$this->getTypes(),
			$this->getEligibleLanguageCodes( true )
		);

		return wpml_collect( $taxonomies )->first();
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
		if ( empty( $languages ) ) {
			return [];
		}

		$languages_part      = $this->buildLanguagesUnion( $languages );
		$acceptable_statuses = ICL_TM_NOT_TRANSLATED . ', ' . ICL_TM_ATE_CANCELLED;
		$element_type        = self::ELEMENT_TYPE_PREFIX . $type;
		$discarded_job       = DiscardedAutomaticJob::joinSql( $this->wpdb->prefix, 'translation_status' );
		$not_a_discarded_job = DiscardedAutomaticJob::whereSql( 'translation_status' );

		$sql = "
			SELECT original_element.element_id, languages.code
			FROM {$this->wpdb->prefix}icl_translations original_element
			INNER JOIN ( {$languages_part} ) as languages
			LEFT JOIN {$this->wpdb->prefix}icl_translations translations
				ON translations.trid = original_element.trid
				AND translations.language_code = languages.code
			LEFT JOIN {$this->wpdb->prefix}icl_translation_status translation_status
				ON translation_status.translation_id = translations.translation_id
			{$discarded_job}

			WHERE original_element.element_type = %s
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
				AND (
					translations.translation_id IS NULL
					OR translation_status.status IN ({$acceptable_statuses})
					OR translation_status.needs_update = 1
				)
				{$not_a_discarded_job}
			ORDER BY original_element.element_id, languages.code
			LIMIT %d
		";

		$result = $this->wpdb->get_results(
			$this->wpdb->prepare(
				$sql,
				$element_type,
				$queueSize
			),
			ARRAY_N
		);

		return Fns::map( Obj::evolve( [ 0 => Cast::toInt() ] ), $result );
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
		unset( $actions );

		$elementType = self::ELEMENT_TYPE_PREFIX . $type;
		$dispatcher  = make( TaxonomyTermJobDispatcher::class, [ ':createdByTranslateEverything' => true ] );

		$models      = [];
		$descriptors = [];
		$preparedByJobId = [];
		$seenPairs   = [];
		$claims      = [];
		$claimOwner  = 'tea-' . bin2hex( random_bytes( 16 ) );
		$claimRepository = $this->termPairClaims();

		foreach ( $elements as $element ) {
			$termTaxonomyId = (int) $element[0];
			$targetLang     = (string) $element[1];

			$pairKey = $termTaxonomyId . '|' . $targetLang;
			if ( isset( $seenPairs[ $pairKey ] ) ) {
				continue;
			}
			$seenPairs[ $pairKey ] = true;

			$sourceLang = $this->getOriginalLanguage( $termTaxonomyId, $elementType );

			if ( $targetLang === $sourceLang ) {
				continue;
			}

			if ( ! $claimRepository->claim( $termTaxonomyId, $targetLang, $claimOwner ) ) {
				continue;
			}
			$claims[] = [ $termTaxonomyId, $targetLang ];

			$prepared = $dispatcher->prepareTermJobWithRecord( $termTaxonomyId, (string) $type, $sourceLang, $targetLang, true );
			$model    = $prepared ? $prepared->getModel() : null;
			if ( $model && $prepared ) {
				$models[]      = $model;
				$preparedByJobId[ (int) $model->id ] = $prepared;
				$descriptors[] = [
					'elementId'   => $termTaxonomyId,
					'lang'        => $targetLang,
					'elementType' => $elementType,
					'jobId'       => (int) $model->id,
				];
			} else {
				$claimRepository->release( $termTaxonomyId, $targetLang, $claimOwner );
			}
		}

		if ( $models ) {
			$dispatchResult = $dispatcher->dispatchToAte( $models );
			$retryableJobIds = array_merge(
				$dispatchResult->getRejectedJobIds(),
				$dispatchResult->getRemainingJobIds()
			);
			$rejected       = array_values(
				array_filter(
					array_map(
						function ( $jobId ) use ( $preparedByJobId ) {
							return $preparedByJobId[ $jobId ] ?? null;
						},
					$retryableJobIds
					)
				)
			);

			$dispatcher->releasePreparedJobs( $rejected );

			if ( $rejected ) {
				$this->markTaxonomyAsUncompleted( (string) $type );
			}

			$durableJobIds = array_flip(
				array_merge(
					$dispatchResult->getAcceptedJobIds(),
					$dispatchResult->getBlockedJobIds(),
					$dispatchResult->getUncertainJobIds()
				)
			);
			$descriptors = array_values(
				array_filter(
					$descriptors,
					function ( array $descriptor ) use ( $durableJobIds ) {
						return isset( $durableJobIds[ (int) $descriptor['jobId'] ] );
					}
				)
			);
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
		$this->updateCompleted(
			function ( array $completed ) use ( $taxonomy ) {
				$completed[ $taxonomy ] = [];

				return $completed;
			}
		);
	}
}
