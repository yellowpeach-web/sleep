<?php

namespace WPML\StringTranslation\Infrastructure\StringPackage\Query;

use WPML\Core\SharedKernel\Component\Translation\Domain\TranslationStatus;
use WPML\StringTranslation\Application\StringPackage\Query\Criteria\SearchPopulatedKindsCriteria;
use WPML\StringTranslation\Application\StringPackage\Query\Criteria\StringPackageCriteria;

trait QueryBuilderTrait {
	protected function sqlStringLiteral( $value ): string {
		return "'" . esc_sql( (string) $value ) . "'";
	}

	private function getSourceLanguageCode( $criteria ): string {
		return $criteria->getSourceLanguageCode()
			? $criteria->getSourceLanguageCode()
			: $this->settingsRepository->getDefaultLanguageCode();
	}

	private function getTargetLanguageCodes( $criteria ): array {
		$languageCodes = $criteria->getTargetLanguageCode() ?
			[  $criteria->getTargetLanguageCode() ] :
			$this->settingsRepository->getActiveLanguageCodes();

		return array_values(
			array_filter(
				$languageCodes,
				function ( $languageCode ) use ( $criteria ) {
					return $languageCode !== $this->getSourceLanguageCode( $criteria );
				}
			)
		);
	}

	private function buildStatusColumn( string $t, string $ts ): string {
		$needsUpdate   = TranslationStatus::NEEDS_UPDATE;
		$complete      = TranslationStatus::COMPLETE;
		$cancelled     = TranslationStatus::NOT_TRANSLATED . ', ' . TranslationStatus::ATE_CANCELED;
		$notTranslated = TranslationStatus::NOT_TRANSLATED;

		return "CASE
			WHEN {$ts}.needs_update = 1 THEN {$needsUpdate}
			WHEN {$t}.source_language_code IS NULL THEN {$complete}
			WHEN {$ts}.status IN ({$cancelled}) THEN IF(
				EXISTS (SELECT 1 FROM {$this->wpdb->prefix}icl_translate_job j WHERE j.rid = {$ts}.rid AND j.translated = 1),
				{$complete},
				{$notTranslated}
			)
			ELSE IFNULL({$ts}.status, {$notTranslated})
		END";
	}

	private function buildPostTitleCondition(
		StringPackageCriteria $criteria
	): string {
		if ( $criteria->getTitle() ) {
			return $this->wpdb->prepare(
				'AND sp.title LIKE %s',
				'%' . $this->wpdb->esc_like( $criteria->getTitle() ) . '%'
			);
		}

		return '';
	}

	private function buildPagination( StringPackageCriteria $criteria ): string {
		return $this->wpdb->prepare(
			'LIMIT %d OFFSET %d',
			$criteria->getLimit(),
			$criteria->getOffset()
		);
	}

	private function buildSortingQueryPart( StringPackageCriteria $criteria ): string {
		$allowedSortingDirections = [ 'DESC', 'ASC' ];
		$direction                = $allowedSortingDirections[0];

		$sortingCriteria = $criteria->getSorting();
		if ( $sortingCriteria && $sortingCriteria['by'] === 'title' ) {
			$sortingOrder = strtoupper( $sortingCriteria['order'] );
			$direction    = in_array( $sortingOrder, $allowedSortingDirections, true ) ? $sortingOrder : $direction;

			return "ORDER BY sp.title {$direction}, sp.ID {$direction}";
		}

		return "ORDER BY sp.ID {$direction}";
	}
}
