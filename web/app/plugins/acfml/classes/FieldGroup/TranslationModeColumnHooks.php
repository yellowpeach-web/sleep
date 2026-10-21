<?php

namespace ACFML\FieldGroup;

use ACFML\Helper\FieldGroup;
use ACFML\TranslationDataColumnHooks;
use WPML\LIB\WP\Hooks;
use function WPML\FP\spreadArgs;

class TranslationModeColumnHooks implements \IWPML_Backend_Action {

	const COLUMN_KEY = 'acfml-translation-options';

	const COLUMN_HOOK_PRIORITY = 11;

	public function add_hooks() {
		if ( FieldGroup::isListScreen() && TranslationDataColumnHooks::shouldRegisterColumn() ) {
			Hooks::onFilter( 'manage_acf-field-group_posts_columns', self::COLUMN_HOOK_PRIORITY )
				->then( spreadArgs( [ $this, 'translationOptionsColumTitle' ] ) );
			Hooks::onAction( 'manage_acf-field-group_posts_custom_column', 10, 2 )
				->then( spreadArgs( [ $this, 'translationOptionsColumContent' ] ) );
		}
	}

	public function translationOptionsColumTitle( $columns ) {
		/* translators: Column heading in the ACF field-groups list, showing how each group is translated. */
		$columns[ self::COLUMN_KEY ] = __( 'Translation Option', 'acfml' );

		return $columns;
	}

	public function translationOptionsColumContent( $column, $postId ) {
		if ( self::COLUMN_KEY === $column ) {
			echo wpml_collect( Mode::getLabels() )
				->map( 'esc_html' )
				->get( Mode::getMode( acf_get_field_group( $postId ) ), '__' );
		}
	}
}
