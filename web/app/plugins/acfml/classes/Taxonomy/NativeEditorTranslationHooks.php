<?php

namespace ACFML\Taxonomy;

use ACFML\Post\NativeEditorTranslationHooks as PostNativeEditorTranslationHooks;
use WPML\API\Sanitize;
use WPML\FP\Obj;
use WPML\LIB\WP\Hooks;

class NativeEditorTranslationHooks implements \IWPML_Backend_Action {

	public function add_hooks() {
		$termId   = isset( $_GET['tag_ID'] ) ? (int) $_GET['tag_ID'] : 0;
		$taxonomy = Sanitize::stringProp( 'taxonomy', $_GET );

		if ( $termId && $taxonomy && self::isTermTranslation( $termId, $taxonomy ) ) {
			PostNativeEditorTranslationHooks::loadFieldLockFilters();

			Hooks::onAction( 'admin_enqueue_scripts' )
				->then( [ PostNativeEditorTranslationHooks::class, 'enqueueAssets' ] );
		}
	}

	private static function isTermTranslation( $termId, $taxonomy ) {
		$term = get_term( $termId, $taxonomy );

		if ( ! $term instanceof \WP_Term ) {
			return false;
		}

		$languageDetails = apply_filters(
			'wpml_element_language_details',
			null,
			[
				'element_id'   => $term->term_taxonomy_id,
				'element_type' => $taxonomy,
			]
		);

		return (bool) Obj::prop( 'source_language_code', $languageDetails );
	}
}
