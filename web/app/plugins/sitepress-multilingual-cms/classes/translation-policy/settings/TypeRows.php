<?php

namespace WPML\TranslationPolicy\Settings;

use WPML\TranslationPolicy\Policy\Precedence;

class TypeRows {

	private $types;

	private $site;

	private $values;

	private $rows = null;

	public function __construct( array $types, array $site, array $values ) {
		$this->types  = $types;
		$this->site   = $site;
		$this->values = $values;
	}

	public function rows(): array {
		if ( null === $this->rows ) {
			$this->rows = [];
			foreach ( $this->types as $slug => $name ) {
				$this->rows[] = $this->row( (string) $slug, $name );
			}
		}

		return $this->rows;
	}

	public function listed(): array {
		return array_values(
			array_filter(
				$this->rows(),
				function ( array $row ) {
					return $row['isListed'];
				}
			)
		);
	}

	public function others(): array {
		return array_values(
			array_filter(
				$this->rows(),
				function ( array $row ) {
					return ! $row['isListed'];
				}
			)
		);
	}

	public function summary(): string {
		$listed = count( $this->listed() );
		if ( 0 === $listed ) {
			return Words::typesSummaryAll();
		}

		$locked  = count(
			array_filter(
				$this->rows(),
				function ( array $row ) {
					return $row['isLocked'];
				}
			)
		);
		$summary = Words::typesSummaryOwn( $listed );

		return $locked ? Words::typesSummaryBoth( $summary, Words::typesSummaryLocked( $locked ) ) : $summary;
	}

	public function isFollowingSite( string $column, array $value, string $postType ): bool {
		if ( ScopeValues::STATE_SITE === $value['state'] ) {
			return true;
		}

		return ScopeValues::STATE_DEFAULT === $value['state']
			&& '' === $value['source']
			&& $value['value'] === $this->siteValueFor( $column, $postType );
	}

	public function siteValueFor( string $column, string $postType ): string {
		$siteValue = $this->site[ $column ]['value'];

		return Precedence::offersValue( $column, $siteValue, $postType ) ? $siteValue : ScopeValues::fallback( $column );
	}

	private function row( string $slug, string $name ): array {
		$values   = $this->values[ $slug ];
		$isFollowingSite = [];
		$isLocked = false;
		$notes    = [];
		foreach ( ScopeValues::COLUMNS as $column ) {
			$isFollowingSite[ $column ] = $this->isFollowingSite( $column, $values[ $column ], $slug );
			$isLocked           = $isLocked || ScopeValues::STATE_LOCK === $values[ $column ]['state'];

			$note = self::note( $values[ $column ] );
			if ( '' !== $note && ! in_array( $note, $notes, true ) ) {
				$notes[] = $note;
			}
		}

		return [
			'slug'     => $slug,
			'name'     => $name,
			'values'   => $values,
			'isFollowingSite' => $isFollowingSite,
			'isListed' => in_array( false, $isFollowingSite, true ),
			'isLocked' => $isLocked,
			'notes'    => $notes,
		];
	}

	private static function note( array $value ): string {
		if ( ScopeValues::STATE_LOCK === $value['state'] ) {
			return Words::lock( $value['source'], $value['reason'] );
		}

		return ScopeValues::STATE_DEFAULT === $value['state'] && '' !== $value['source'] ? Words::pluginDefault() : '';
	}
}
