<?php

namespace WPML\TranslationPolicy\Policy;

class LegacyListEditor implements ListEditorInterface {

	public function usesNativeEditor( $postId, callable $listPostIds ): bool {
		return \WPML_TM_Post_Edit_TM_Editor_Mode::uses_native_editor( $postId );
	}
}
