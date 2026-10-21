<?php

namespace WPML\StringTranslation\Infrastructure\StringPackage\Query;

use WPML\Core\SharedKernel\Component\Translation\Domain\ReviewStatus;
use WPML\Core\SharedKernel\Component\Translation\Domain\TranslationStatus;
use WPML\StringTranslation\Application\StringPackage\Query\Criteria\SearchPopulatedKindsCriteria;
use WPML\StringTranslation\Application\StringPackage\Query\Criteria\StringPackageCriteria;

trait TranslationStatusQueryBuilderTrait {
	use QueryBuilderTrait;

	private function buildTranslationStatusCondition( $criteria, array $targetLanguageCodes ): string {
		$statuses = array_map( 'intval', $criteria->getTranslationStatuses() );
		if ( ! $statuses || ! $targetLanguageCodes ) {
			return '1';
		}

		$prefix      = $this->wpdb->prefix;
		$inLanguages = wpml_prepare_in( $targetLanguageCodes, '%s' );
		$inStatuses  = wpml_prepare_in( $statuses, '%d' );

		$conditions = [
			"(EXISTS (
				SELECT 1
				FROM {$prefix}icl_translations t
				LEFT JOIN {$prefix}icl_translation_status ts
					ON ts.translation_id = t.translation_id
				WHERE t.trid = source_t.trid
					AND t.language_code IN ({$inLanguages})
					AND {$this->buildShownStatus( 't', 'ts' )} IN ({$inStatuses})
			)) = 1",
		];

		if ( in_array( TranslationStatus::NOT_TRANSLATED, $statuses, true ) ) {
			$conditions[] = "(
				SELECT COUNT(*)
				FROM {$prefix}icl_translations t
				WHERE t.trid = source_t.trid
					AND t.language_code IN ({$inLanguages})
			) < " . count( $targetLanguageCodes );
		}

		return '(' . implode( ' OR ', $conditions ) . ')';
	}

	private function buildShownStatus( string $t, string $ts ): string {
		$column       = $this->buildStatusColumn( $t, $ts );
		$reviewStatus = ReviewStatus::NEEDS_REVIEW;
		$needsReview  = TranslationStatus::NEEDS_REVIEW;
		$unsolvable   = TranslationStatus::ATE_UNSOLVABLE;

		return "(CASE
			WHEN {$ts}.review_status = '{$reviewStatus}' THEN IF(({$column}) = {$unsolvable}, {$unsolvable}, {$needsReview})
			ELSE ({$column})
		END)";
	}
}
