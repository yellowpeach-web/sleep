<?php

namespace WPML\PB\TranslationJob;

class Editor {

	private static $isATE = null;

	public static function isATE() {
		if ( null !== self::$isATE ) {
			return self::$isATE;
		}

		return ICL_TM_TMETHOD_ATE === apply_filters( 'wpml_sub_setting', false, 'translation-management', 'doc_translation_method' );
	}

	public static function withJobOf( $originalPostId, $language, callable $callback ) {
		$previous    = self::$isATE;
		self::$isATE = self::isJobATE( $originalPostId, $language );

		try {
			return $callback();
		} finally {
			self::$isATE = $previous;
		}
	}

	private static function isJobATE( $originalPostId, $language ) {
		$trid   = apply_filters( 'wpml_element_trid', null, $originalPostId, 'post_' . get_post_type( $originalPostId ) );
		$editor = $trid ? strtolower( (string) wpml_load_core_tm()->get_translation_job_editor( $trid, $language ) ) : '';

		if ( \WPML_TM_Editors::ATE === $editor ) {
			return true;
		}

		return \WPML_TM_Editors::WPML === $editor ? false : null;
	}
}
