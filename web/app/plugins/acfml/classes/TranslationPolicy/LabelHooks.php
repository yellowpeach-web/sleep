<?php

namespace ACFML\TranslationPolicy;

use ACFML\FieldGroup\Cache;
use ACFML\FieldGroup\Mode;
use WPML\FP\Obj;
use WPML\FP\Relation;
use WPML\TranslationPolicy\Policy\Constraints;

class LabelHooks implements \IWPML_Action {

	public function add_hooks() {
		Constraints::registerLabel( LockRows::SOURCE, [ $this, 'label' ] );
	}

	public function label( $reason, $postId ) {
		$group = (int) $postId > 0
			? wpml_collect( Cache::getForPost( (int) $postId ) )->first( Relation::propEq( Mode::KEY, Mode::LOCALIZATION ) )
			: null;

		if ( ! $group ) {
			return __( 'This content must be translated manually due to the translation option you selected for its ACF field group.', 'acfml' );
		}

		return sprintf(
			/* translators: %1$s: ACF field group name. */
			__( 'This content must be translated manually due to the translation option you selected for the "%1$s" field group.', 'acfml' ),
			Obj::propOr( '', 'title', $group )
		);
	}
}
