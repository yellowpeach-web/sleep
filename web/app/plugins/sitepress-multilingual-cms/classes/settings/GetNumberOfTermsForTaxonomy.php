<?php

namespace WPML\TM\Settings;

use WPML\Collect\Support\Collection;
use WPML\Element\API\Languages;
use WPML\TM\API\ATE\CachedLanguageMappings;
use WPML\TM\ATE\TranslateEverything\UntranslatedTerms;

class GetNumberOfTermsForTaxonomy {

	public function run( Collection $data, \wpdb $wpdb ) {
		$enabling = $this->sanitizeSlugs( $data->get( 'enabling', [] ) );

		$targetLanguages = array_values(
			array_diff( CachedLanguageMappings::geCodesEligibleForAutomaticTranslations(), [ Languages::getDefaultCode() ] )
		);

		$toTranslate = $enabling
			? $this->countUntranslatedTerms( $wpdb, $enabling, $targetLanguages )
			: [
				'terms'        => 0,
				'translations' => 0,
			];

		return [
			'terms'        => $toTranslate['terms'],
			'translations' => $toTranslate['translations'],
			'languages'    => count( $targetLanguages ),
		];
	}

	private function countUntranslatedTerms( \wpdb $wpdb, array $taxonomies, array $targetLanguages ) {
		if ( ! $targetLanguages ) {
			return [
				'terms'        => 0,
				'translations' => 0,
			];
		}

		$languagesPart = implode(
			' UNION ALL ',
			array_map(
				function ( $code ) {
					return "SELECT '" . esc_sql( $code ) . "' AS code";
				},
				$targetLanguages
			)
		);

		$elementTypesIn      = wpml_prepare_in( $this->toElementTypes( $taxonomies ) );
		$acceptableStatuses  = ICL_TM_NOT_TRANSLATED . ', ' . ICL_TM_ATE_CANCELLED;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS translations, COUNT(DISTINCT original_element.element_id) AS terms
				FROM {$wpdb->prefix}icl_translations original_element
				INNER JOIN ( {$languagesPart} ) as languages
				LEFT JOIN {$wpdb->prefix}icl_translations translations
					ON translations.trid = original_element.trid
					AND translations.language_code = languages.code
				LEFT JOIN {$wpdb->prefix}icl_translation_status translation_status
					ON translation_status.translation_id = translations.translation_id
				WHERE original_element.element_type IN ({$elementTypesIn})
					AND original_element.source_language_code IS NULL
					AND original_element.language_code = %s
					AND languages.code <> original_element.language_code
					AND (
						translations.translation_id IS NULL
						OR translation_status.status IN ({$acceptableStatuses})
						OR translation_status.needs_update = 1
					)",
				Languages::getDefaultCode()
			)
		);

		return [
			'terms'        => $row ? (int) $row->terms : 0,
			'translations' => $row ? (int) $row->translations : 0,
		];
	}

	private function toElementTypes( array $taxonomies ) {
		return array_map(
			function ( $taxonomy ) {
				return UntranslatedTerms::ELEMENT_TYPE_PREFIX . $taxonomy;
			},
			$taxonomies
		);
	}

	private function sanitizeSlugs( $slugs ) {
		if ( ! is_array( $slugs ) ) {
			return [];
		}

		return array_values(
			array_unique(
				array_filter(
					array_map( 'sanitize_key', array_filter( $slugs, 'is_string' ) )
				)
			)
		);
	}
}
