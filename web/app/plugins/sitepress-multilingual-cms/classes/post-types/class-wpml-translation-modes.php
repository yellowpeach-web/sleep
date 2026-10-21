<?php

use WPML\FP\Lst;


class WPML_Translation_Modes {

	public function is_translatable_mode( $mode ) {
		return Lst::includes(
			(int) $mode,
			[ WPML_CONTENT_TYPE_TRANSLATE, WPML_CONTENT_TYPE_DISPLAY_AS_IF_TRANSLATED ]
		);
	}

	public function get_options_for_post_type( $post_type_label ) {
		return [
			/* translators: Option in the dropdown that says how a content type is handled: leave it out of translation. %s: the name of that content type, for example Posts. */
			WPML_CONTENT_TYPE_DONT_TRANSLATE           => sprintf( __( "Do not make '%s' translatable", 'sitepress' ), $post_type_label ),
			/* translators: Option in the dropdown that says how a content type is handled: let it be translated. %s: the name of that content type, for example Posts. */
			WPML_CONTENT_TYPE_TRANSLATE                => sprintf( __( "Make '%s' translatable", 'sitepress' ), $post_type_label ),
			/* translators: Option in the dropdown that says how a content type is handled: show the original where a translation is missing, as if it were translated. %s: the name of that content type, for example Posts. */
			WPML_CONTENT_TYPE_DISPLAY_AS_IF_TRANSLATED => sprintf( __( "Make '%s' appear as translated", 'sitepress' ), $post_type_label ),
		];
	}

	public function get_options() {
		$formatHeading = function ( $a, $b ) {
			return $a . "<br/><span class='explanation-text'>" . $b . '</span>';
		};

		return [
			WPML_CONTENT_TYPE_TRANSLATE                => $formatHeading(
				/* translators: Name of the setting that lets a content type be translated, shown as the heading of a group of choices. Adjective. */
				esc_html__( 'Translatable', 'sitepress' ),
				/* translators: Second line under the "Translatable" choice, explaining it: only items that have a translation are shown. It starts in lower case because it follows that choice. */
				esc_html__( 'only show translated items', 'sitepress' )
			),
			WPML_CONTENT_TYPE_DISPLAY_AS_IF_TRANSLATED => $formatHeading(
				/* translators: Name of the setting that lets a content type be translated, shown as the heading of a group of choices. Adjective. */
				esc_html__( 'Translatable', 'sitepress' ),
				/* translators: Second line under a choice, explaining it: the translation is shown when there is one, and the original otherwise. It starts in lower case because it follows that choice. */
				esc_html__( 'use translation if available or fallback to default language', 'sitepress' )
			),
			WPML_CONTENT_TYPE_DONT_TRANSLATE           => $formatHeading(
				/* translators: Name of the setting that keeps a content type out of translation, shown as the heading of a group of choices. Adjective. */
				esc_html__( 'Not translatable', 'sitepress' ),
				'&nbsp;'
			)
		];
	}
}
