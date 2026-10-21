<?php

use WPML\PB\ElementUid\Knowledge;

class WPML_PB_Last_Translation_Edit_Mode {

	const POST_META_KEY      = '_last_translation_edit_mode';
	const FACT               = 'last_translation_edit_mode';
	const NATIVE_EDITOR      = 'native-editor';
	const TRANSLATION_EDITOR = 'translation-editor';

	public static function is_native_editor( $post_id ) {
		return self::get_last_mode( $post_id ) === self::NATIVE_EDITOR;
	}

	public static function is_translation_editor( $post_id ) {
		return self::get_last_mode( $post_id ) === self::TRANSLATION_EDITOR;
	}

	private static function get_last_mode( $post_id ) {
		$knowledge = new Knowledge();

		if ( ! $knowledge->isAvailable() ) {
			return get_post_meta( $post_id, self::POST_META_KEY, true );
		}

		$mode = $knowledge->get( $post_id, null, self::FACT );

		return null === $mode ? self::adoptMeta( $post_id, $knowledge ) : $mode;
	}

	private static function adoptMeta( $post_id, Knowledge $knowledge ) {
		$mode = get_post_meta( $post_id, self::POST_META_KEY, true );

		if ( is_string( $mode ) && '' !== $mode ) {
			$knowledge->set( $post_id, null, self::FACT, $mode );
			delete_post_meta( $post_id, self::POST_META_KEY );
		}

		return $mode;
	}

	public static function set_native_editor( $post_id ) {
		self::set_mode( $post_id, self::NATIVE_EDITOR );
	}

	public static function set_translation_editor( $post_id ) {
		self::set_mode( $post_id, self::TRANSLATION_EDITOR );
	}

	private static function set_mode( $post_id, $mode ) {
		$knowledge = new Knowledge();

		if ( ! $knowledge->isAvailable() ) {
			update_post_meta( $post_id, self::POST_META_KEY, $mode );

			return;
		}

		$knowledge->set( $post_id, null, self::FACT, $mode );

		if ( '' !== (string) get_post_meta( $post_id, self::POST_META_KEY, true ) ) {
			delete_post_meta( $post_id, self::POST_META_KEY );
		}
	}
}
