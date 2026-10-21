<?php

namespace WPML\WPSEO\YoastSEO\Terms;

use WPML\Element\API\Translations;
use WPML\FP\Maybe;
use WPML\FP\Obj;
use WPML\LIB\WP\Hooks;
use WPML\WPSEO\YoastSEO\Terms\Meta\Hooks as TermMetaHooks;
use WPSEO_Taxonomy_Meta;
use function WPML\FP\spreadArgs;

class CopyOnceHooks implements \IWPML_Backend_Action, \IWPML_Frontend_Action {

	public function add_hooks() {
		Hooks::onAction( 'created_term', 10, 3 )
			->then( spreadArgs( [ $this, 'copyOnceTermMeta' ] ) );
	}

	public function copyOnceTermMeta( $termId, $termTaxonomyId, $taxonomy ) {
		TermMetaHooks::withStoredValues(
			function () use ( $termId, $termTaxonomyId, $taxonomy ) {
				TermMetaHooks::withoutTermAdjustId(
					function () use ( $termId, $termTaxonomyId, $taxonomy ) {
						$this->copyOnceFromOriginalTerm( (int) $termId, (int) $termTaxonomyId, (string) $taxonomy );
					}
				);
			}
		);
	}

	private function copyOnceFromOriginalTerm( int $termId, int $termTaxonomyId, string $taxonomy ) {
		$wpSeoTaxonomyMeta = WPSEO_Taxonomy_Meta::get_instance();

		$getTermByElement = function ( $translationElement ) use ( $taxonomy ) {
			return get_term_by( 'term_taxonomy_id', $translationElement->element_id, $taxonomy );
		};

		$getOriginalMeta = function ( $originalTerm ) use ( $wpSeoTaxonomyMeta, $taxonomy ) {
			return $wpSeoTaxonomyMeta->get_term_meta( $originalTerm, $taxonomy );
		};

		$filterValuesToCopyOnce = Obj::pick( self::getKeysToCopyOnce() );

		$setTranslationMeta = function ( $filteredMeta ) use ( $wpSeoTaxonomyMeta, $termId, $taxonomy ) {
			$wpSeoTaxonomyMeta->set_values( $termId, $taxonomy, $filteredMeta );
		};

		Maybe::fromNullable( Translations::getOriginal( $termTaxonomyId, "tax_$taxonomy" ) )
			->map( $getTermByElement )
			->map( $getOriginalMeta )
			->map( $filterValuesToCopyOnce )
			->map( $setTranslationMeta );
	}

	private static function getKeysToCopyOnce() {
		$keys = [
			'wpseo_noindex',
			'wpseo_opengraph-image',
			'wpseo_opengraph-image-id',
			'wpseo_twitter-image',
			'wpseo_twitter-image-id',
		];

		return (array) apply_filters( 'wpmlseo_yoast_term_meta_keys_to_copy_once', $keys );
	}
}
