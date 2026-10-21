<?php

namespace ACFML\Post;

use function WPML\Container\make;

class EditorHooksFactory implements \IWPML_Backend_Action_Loader, \IWPML_Deferred_Action_Loader {

	public function get_load_action() {
		return 'wp_loaded';
	}

	public function create() {
		global $pagenow;

		$isPostEditScreen = in_array( $pagenow, [ 'post.php', 'post-new.php' ], true );

		if ( $isPostEditScreen ) {
			return [
				new NativeEditorTranslationHooks( make( \WPML_ACF_Worker::class ) ),
				new MixedFieldGroupModesHooks(),
			];
		}

		return null;
	}
}
