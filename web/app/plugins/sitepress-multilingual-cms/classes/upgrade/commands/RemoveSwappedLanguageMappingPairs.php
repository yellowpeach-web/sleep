<?php

namespace WPML\Upgrade\Commands;

use WPML\FP\Obj;
use WPML\TM\API\ATE\LanguageMappings;

class RemoveSwappedLanguageMappingPairs extends RemoveLanguageMappingsOntoDefaultLanguage {

	const RETRY_TRANSIENT = 'wpml_swapped_mapping_pairs_retry';

	const ATTEMPTS_OPTION = 'wpml_swapped_mapping_pairs_attempts';

	private $secondaryCodes;

	public function __construct( array $args = array() ) {
		parent::__construct( $args );
		$this->secondaryCodes = isset( $args[2] ) && is_array( $args[2] ) ? $args[2] : null;
	}

	protected function loneDefaultMappingKeys( array $mappings, $defaultCode ) {
		$secondaryCodes = $this->secondaryCodes;
		$keys           = array();
		foreach ( $mappings as $key => $mapping ) {
			if ( ! is_array( $mapping ) && ! is_object( $mapping ) ) {
				continue;
			}
			$verdict = LanguageMappings::defaultOntoSecondaryVerdict(
				(string) Obj::propOr( '', 'sourceCode', $mapping ),
				(string) Obj::propOr( '', 'targetCode', $mapping ),
				$defaultCode,
				$secondaryCodes
			);
			if ( LanguageMappings::VERDICT_REFUSE === $verdict ) {
				$keys[] = $key;
			}
		}

		return $keys;
	}
}
