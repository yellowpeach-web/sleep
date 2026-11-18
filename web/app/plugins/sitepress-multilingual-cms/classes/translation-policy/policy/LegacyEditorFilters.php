<?php

namespace WPML\TranslationPolicy\Policy;

class LegacyEditorFilters implements EditorFiltersInterface {

	public function useTmEditor( $useTmEditor, $postId ) {
		return apply_filters( self::USE_TM_EDITOR, $useTmEditor, $postId );
	}

	public function blockedPosts( $postIds ) {
		return apply_filters( self::EXCLUDE_POSTS, [], $postIds );
	}

	public function prime( array $postIds ) {
	}
}
