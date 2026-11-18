<?php

namespace WPML\TranslationPolicy\Policy;

interface ListEditorInterface {

	public function usesNativeEditor( $postId, callable $listPostIds ): bool;
}
