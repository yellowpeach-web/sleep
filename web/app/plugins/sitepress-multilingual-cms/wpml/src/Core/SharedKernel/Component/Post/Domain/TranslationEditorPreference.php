<?php

namespace WPML\Core\SharedKernel\Component\Post\Domain;

class TranslationEditorPreference {

  const POST_META_KEY_USE_NATIVE        = '_wpml_post_translation_editor_native';
  const TM_KEY_FOR_POST_TYPE_USE_NATIVE = 'post_translation_editor_native_for_post_type';
  const TM_KEY_GLOBAL_USE_NATIVE        = 'post_translation_editor_native';

  const POST_META_KEY_USE_WPML        = '_wpml_post_translation_editor_wpml';
  const TM_KEY_FOR_POST_TYPE_USE_WPML = 'post_translation_editor_wpml_for_post_type';
  const TM_KEY_GLOBAL_USE_WPML        = 'post_translation_editor_wpml';

  const POST_META_KEY_EDITOR        = '_wpml_post_translation_editor';
  const TM_KEY_FOR_POST_TYPE_EDITOR = 'post_translation_editor_for_post_type';
  const TM_KEY_GLOBAL_EDITOR        = 'post_translation_editor';

  const EDITOR_NATIVE    = 'native';
  const EDITOR_WPML      = 'wpml';
  const EDITOR_DASHBOARD = 'dashboard';

}
