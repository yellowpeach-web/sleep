<?php

namespace WPML\StringTranslation\Infrastructure\WordPress\HookHandler\TranslateEverything;

use WPML\StringTranslation\Infrastructure\TranslateEverything\UntranslatedTaxonomyLabelStrings;
use WPML\StringTranslation\Infrastructure\TranslateEverything\UntranslatedTaxonomyLabelStringsFactory;
use WPML\StringTranslation\Infrastructure\WordPress\HookHandler\AbstractFilterHookHandler;

class AddTaxonomyLabelStringIdsToEstimateFilter extends AbstractFilterHookHandler {

	const FILTER_NAME     = 'wpml_tea_estimate_taxonomy_label_string_ids';
	const FILTER_ARGS     = 1;
	const FILTER_PRIORITY = 10;

	private $strategy;

	protected function onFilter( ...$args ) {
		$ids = is_array( $args[0] ?? null ) ? $args[0] : [];

		return array_values( array_unique( array_merge( $ids, $this->getStrategy()->getLabelStringIds() ) ) );
	}

	protected function getStrategy(): UntranslatedTaxonomyLabelStrings {
		if ( ! $this->strategy ) {
			$this->strategy = ( new UntranslatedTaxonomyLabelStringsFactory() )->create();
		}

		return $this->strategy;
	}
}
