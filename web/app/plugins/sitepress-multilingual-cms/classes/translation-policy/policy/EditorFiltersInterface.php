<?php

namespace WPML\TranslationPolicy\Policy;

interface EditorFiltersInterface {

	const USE_TM_EDITOR = 'wpml_use_tm_editor';

	const EXCLUDE_POSTS = 'wpml_tm_editor_exclude_posts';

	public function useTmEditor( $useTmEditor, $postId );

	public function blockedPosts( $postIds );

	public function prime( array $postIds );
}
