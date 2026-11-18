<?php

namespace WPML\TaxonomyTermTranslation;

class TermTranslationInsert {

	public static function insert( $name, $taxonomy, $args, $trid, $languageCode, $sourceLanguageCode, $isCopy = false ) {
		global $sitepress;

		if ( ! $sitepress ) {
			return wp_insert_term( $name, $taxonomy, $args );
		}

		$sitepressHandler = [ $sitepress, 'create_term' ];
		$hadCreateTerm    = false !== has_action( 'create_term', $sitepressHandler );
		$hadEditTerm      = false !== has_action( 'edit_term', $sitepressHandler );

		if ( $hadCreateTerm ) {
			remove_action( 'create_term', $sitepressHandler, 1 );
		}
		if ( $hadEditTerm ) {
			remove_action( 'edit_term', $sitepressHandler, 1 );
		}

		$done    = false;
		$handler = function ( $termId, $ttId, $createdTaxonomy ) use ( &$done, $sitepress, $taxonomy, $trid, $languageCode, $sourceLanguageCode ) {
			if ( $done || $createdTaxonomy !== $taxonomy ) {
				return;
			}
			$done = true;

			$sitepress->set_element_language_details(
				$ttId,
				'tax_' . $taxonomy,
				$trid,
				$languageCode,
				$sourceLanguageCode
			);
		};

		add_action( 'create_term', $handler, 1, 3 );

		$result = wp_insert_term( $name, $taxonomy, $args );

		remove_action( 'create_term', $handler, 1 );

		if ( $hadCreateTerm ) {
			add_action( 'create_term', $sitepressHandler, 1, 3 );
		}
		if ( $hadEditTerm ) {
			add_action( 'edit_term', $sitepressHandler, 1, 3 );
		}

		if ( $isCopy && $done && is_array( $result ) && isset( $result['term_taxonomy_id'] ) ) {
			CopiedTermMarker::mark( (int) $result['term_taxonomy_id'], (string) $taxonomy );
		}

		return $result;
	}
}
