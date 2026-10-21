<?php

namespace ACFML\TranslationEditor;

use ACFML\FieldGroup\Cache as FieldGroupCache;
use ACFML\FieldGroup\Mode;
use WPML\FP\Relation;
use WPML\LIB\WP\Hooks;
use WPML\FP\Obj;
use function WPML\FP\spreadArgs;

class DisableHooks implements \IWPML_Backend_Action, \IWPML_Frontend_Action {

	public function add_hooks() {
		Hooks::onFilter( 'wpml_tm_editor_exclude_posts', 10, 2 )
			->then( spreadArgs( [ $this, 'disableTranslationEditor' ] ) );
	}

	public function disableTranslationEditor( $excludedPosts, $postIds ) {
		if ( ! FieldGroupCache::hasLocalizationGroup() ) {
			return $excludedPosts;
		}

		$getFirstFieldGroupWithLocalization = function( $postId ) {
			return wpml_collect( FieldGroupCache::getForPost( $postId ) )
				->first( Relation::propEq( Mode::KEY, Mode::LOCALIZATION ) );
		};

		$appendNewExcludedIds = function( $carry, $postId ) use ( $getFirstFieldGroupWithLocalization ) {
			$group = $getFirstFieldGroupWithLocalization( $postId );

			if ( $group ) {
				$carry[ $postId ] = sprintf(
				/* translators: %1$s: ACF field group name. */
					esc_html__( 'This content must be translated manually due to the translation option you selected for the "%1$s" field group.', 'acfml' ),
					esc_html( Obj::propOr( '', 'title', $group ) )
				);
			}

			return $carry;
		};

		return wpml_collect( $postIds )
			->diff( array_keys( $excludedPosts ) )
			->reduce( $appendNewExcludedIds, $excludedPosts );
	}
}
