<?php

namespace WPML\TM\Settings;

class PreferenceOutcomeSentences {

	const KIND_POST = 'post';
	const KIND_TERM = 'term';

	public static function all( $kind = self::KIND_POST ) {
		return [
			/* translators: Under the "Don't translate" head of the custom-field preference table: what a visitor sees on the translation for that field. */
			WPML_IGNORE_CUSTOM_FIELD    => __( 'The translation shows nothing for this field.', 'sitepress' ),
			/* translators: Under the "Copy" head of the custom-field preference table: the translation always shows the original's value, including every later change. */
			WPML_COPY_CUSTOM_FIELD      => __( "The translation shows the original's value and follows every change to it.", 'sitepress' ),
			/* translators: Under the "Copy once" head of the custom-field preference table: the value is put in the translation once, and edited there afterwards. */
			WPML_COPY_ONCE_CUSTOM_FIELD => __( "The translation starts with the original's value and keeps its own afterwards.", 'sitepress' ),
			WPML_TRANSLATE_CUSTOM_FIELD => self::KIND_TERM === $kind
				/* translators: Under the "Translate" head of the term meta preference table: the field is translated with the rest of the term (a category, a tag or another taxonomy term). */
				? __( 'The translation has its own value, translated with the term.', 'sitepress' )
				/* translators: Under the "Translate" head of the custom-field preference table: the field is translated with the rest of the post. */
				: __( 'The translation has its own value, translated with the post.', 'sitepress' ),
		];
	}

	public static function forMode( $mode, $kind = self::KIND_POST ) {
		$all = self::all( $kind );

		return isset( $all[ (int) $mode ] ) ? $all[ (int) $mode ] : '';
	}
}
