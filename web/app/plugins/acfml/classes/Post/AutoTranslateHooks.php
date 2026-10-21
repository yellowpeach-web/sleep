<?php

namespace ACFML\Post;

use ACFML\FieldGroup\HandTranslatedPosts;
use WPML\LIB\WP\Hooks;
use function WPML\FP\spreadArgs;

class AutoTranslateHooks implements \IWPML_Backend_Action, \IWPML_Frontend_Action {

	public function add_hooks() {
		Hooks::onFilter( 'wpml_exclude_post_from_auto_translate', 10, 2 )
			->then( spreadArgs( [ $this, 'excludeFromAutoTranslation' ] ) );
	}

	public function excludeFromAutoTranslation( $isExcluded, $postId ) {
		if ( $isExcluded ) {
			return true;
		}

		return HandTranslatedPosts::includes( (int) $postId );
	}
}
