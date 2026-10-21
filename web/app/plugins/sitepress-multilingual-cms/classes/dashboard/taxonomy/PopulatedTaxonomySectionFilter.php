<?php

namespace WPML\TM\Dashboard\Taxonomy;

class PopulatedTaxonomySectionFilter implements \IWPML_Backend_Action, \IWPML_REST_Action, \IWPML_AJAX_Action {

	const SECTION_ID = RegisterTaxonomyItemSection::SECTION_ID;

	const STATUS_NOT_TRANSLATED = 0;
	const STATUS_WAITING        = 1;
	const STATUS_IN_PROGRESS    = 2;
	const STATUS_NEEDS_UPDATE   = 3;
	const STATUS_READY          = 4;
	const STATUS_DUPLICATE      = 9;
	const STATUS_COMPLETE       = 10;
	const STATUS_ATE_RETRY      = 40;

	public function add_hooks() {
		add_filter( 'wpml_tm_populated_item_sections', [ $this, 'filter' ], 9, 5 );
	}

	public function filter(
		$itemSectionIds = [],
		$publicationStatus = null,
		$sourceLanguageCode = '',
		$targetLanguageCode = null,
		$translationStatuses = []
	) {
		$itemSectionIds = (array) $itemSectionIds;

		if ( ! in_array( self::SECTION_ID, $itemSectionIds, true ) ) {
			return $itemSectionIds;
		}

		if ( $this->hasMatchingItems( $targetLanguageCode, (array) $translationStatuses ) ) {
			return $itemSectionIds;
		}

		return array_values(
			array_filter(
				$itemSectionIds,
				function ( $id ) {
					return $id !== self::SECTION_ID;
				}
			)
		);
	}

	private function hasMatchingItems( $targetLanguageCode, array $translationStatuses ): bool {
		try {
			$data = $this->getDashboardData();
		} catch ( \Throwable $e ) {
			return true;
		}

		$states = [];

		foreach ( (array) ( $data['rows'] ?? [] ) as $row ) {
			if ( 0 === (int) ( $row['total'] ?? 0 ) ) {
				continue;
			}
			foreach ( (array) ( $row['languages'] ?? [] ) as $state ) {
				$states[] = $state;
			}
		}

		foreach ( (array) ( $data['labelRows'] ?? [] ) as $row ) {
			foreach ( (array) ( $row['languages'] ?? [] ) as $state ) {
				$states[] = $state;
			}
		}

		if ( $targetLanguageCode ) {
			$states = array_values(
				array_filter(
					$states,
					function ( $state ) use ( $targetLanguageCode ) {
						return isset( $state['code'] ) && $state['code'] === $targetLanguageCode;
					}
				)
			);
		}

		if ( empty( $translationStatuses ) ) {
			return ! empty( $states );
		}

		$translationStatuses = array_map( 'intval', $translationStatuses );

		foreach ( $states as $state ) {
			$codes = $this->mapStatusToCodes( (string) ( $state['status'] ?? '' ) );
			if ( array_intersect( $codes, $translationStatuses ) ) {
				return true;
			}
		}

		return false;
	}

	protected function getDashboardData(): array {
		return ( new TaxonomyDashboardData() )->get();
	}

	private function mapStatusToCodes( string $status ): array {
		switch ( $status ) {
			case 'complete':
				return [ self::STATUS_COMPLETE, self::STATUS_READY, self::STATUS_DUPLICATE, self::STATUS_ATE_RETRY ];
			case 'in_progress':
				return [ self::STATUS_WAITING, self::STATUS_IN_PROGRESS ];
			case 'preparing':
				return [ self::STATUS_IN_PROGRESS ];
			case 'needs_update':
				return [ self::STATUS_NEEDS_UPDATE ];
			case 'partial':
				return [
					self::STATUS_NOT_TRANSLATED,
					self::STATUS_COMPLETE,
					self::STATUS_READY,
					self::STATUS_DUPLICATE,
					self::STATUS_ATE_RETRY,
				];
			case 'missing':
			default:
				return [ self::STATUS_NOT_TRANSLATED ];
		}
	}
}
