<?php

namespace WPML\TM\Settings;

class ReapplyToTerms {

	const TERM_TRANSITIONS = [ PreferenceChangeSet::COPY, PreferenceChangeSet::COPY_ONCE ];

	private $affectedTerms;

	private $termMetaCopy;

	public function __construct( AffectedTermsQuery $affectedTerms, TermMetaCopy $termMetaCopy ) {
		$this->affectedTerms = $affectedTerms;
		$this->termMetaCopy  = $termMetaCopy;
	}

	public function countAffected( PreferenceChangeSet $changeSet ): int {
		return $this->affectedTerms->countAffected( $this->termFieldNames( $changeSet ) );
	}

	public function page( PreferenceChangeSet $changeSet, int $lastTermId, int $limit ): array {
		return $this->affectedTerms->page( $this->termFieldNames( $changeSet ), $lastTermId, $limit );
	}

	public function apply( array $termIds, PreferenceChangeSet $changeSet, array $processed ): array {
		$namesByTransition = self::termChanges( $changeSet )->namesByTransition();

		foreach ( $termIds as $termId ) {
			$termMeta = get_term_meta( (int) $termId );
			$termMeta = is_array( $termMeta ) ? $termMeta : [];

			foreach ( $namesByTransition as $transition => $names ) {
				$present = array_values( array_intersect( $this->onlyTermFields( $names ), array_keys( $termMeta ) ) );

				if ( ! $present ) {
					continue;
				}

				$processed[ $transition ] = ( isset( $processed[ $transition ] ) ? (int) $processed[ $transition ] : 0 ) + 1;

				foreach ( $this->affectedTerms->termTaxonomyIds( (int) $termId ) as $termTaxonomyId ) {
					$this->termMetaCopy->copyToTranslations( $termTaxonomyId, $present, PreferenceChangeSet::COPY_ONCE === $transition );
				}
			}
		}

		return $processed;
	}

	public static function termChanges( PreferenceChangeSet $changeSet ): PreferenceChangeSet {
		return $changeSet->onlyTransitions( self::TERM_TRANSITIONS );
	}

	private function termFieldNames( PreferenceChangeSet $changeSet ): array {
		return $this->onlyTermFields( self::termChanges( $changeSet )->fieldNames() );
	}

	private function onlyTermFields( array $names ): array {
		return array_values( array_filter( $names, [ $this->termMetaCopy, 'isTermField' ] ) );
	}
}
