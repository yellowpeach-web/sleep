<?php

use WPML\TM\StringTranslation\Strings;

class WPML_Remote_String_Translation {

	public static function get_string_status_labels() {
		return array(
			/* translators: Status of a text: every language has been translated. */
			ICL_TM_COMPLETE                => __( 'Translation complete', 'sitepress' ),
			/* translators: Status of a text: some languages have been translated and others have not. */
			ICL_STRING_TRANSLATION_PARTIAL => __( 'Partial translation', 'sitepress' ),
			ICL_TM_NEEDS_UPDATE            => __( 'Translation needs update',
				'sitepress' ),
			/* translators: Status of a piece of content: it has no translation in that language yet. */
			ICL_TM_NOT_TRANSLATED          => __( 'Not translated', 'sitepress' ),
			ICL_TM_WAITING_FOR_TRANSLATOR  => __( 'Waiting for translator / In progress',
				'sitepress' ),
			ICL_TM_TRANSLATION_READY_TO_DOWNLOAD => __('Translation ready to download', 'sitepress'),
		);
	}

	public static function get_string_status_label( $status ) {
		$string_translation_states_enumeration = self::get_string_status_labels();
		if ( isset( $string_translation_states_enumeration[ $status ] ) ) {
			return $string_translation_states_enumeration[ $status ];
		}

		return false;
	}
}
