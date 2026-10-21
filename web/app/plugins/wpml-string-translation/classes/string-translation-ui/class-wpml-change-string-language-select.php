<?php

class WPML_Change_String_Language_Select {
	private $wpdb;

	private $sitepress;

	public function __construct( wpdb $wpdb, SitePress $sitepress ) {
		$this->wpdb      = $wpdb;
		$this->sitepress = $sitepress;
	}


	public function configured_languages() {
		return \WPML\ST\StringTranslationUI\ConfiguredLanguages::rows( $this->sitepress );
	}

	public function show() {

		$options = array(
			'id'                 => 'icl-st-change-lang-selected',
			'class'              => 'wpml-select2-button',
			'please_select_text' => __( 'Change the language of selected strings', 'wpml-string-translation' ),
			'disabled'           => true,
			'languages'          => $this->configured_languages(),
			'echo'               => true,
		);

		$lang_selector = new WPML_Simple_Language_Selector( $this->sitepress );
		$lang_selector->render( $options );

	}

	public function change_language_of_strings( $strings, $lang ) {
		$package_translation = new WPML_Package_Helper();
		$response            = $package_translation->change_language_of_strings( $strings, $lang );

		if ( $response['success'] && $strings ) {
			$wpdb = $this->wpdb;

			$previous_languages = (array) $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT language FROM {$wpdb->prefix}icl_strings WHERE id IN (" . implode( ', ', array_fill( 0, count( $strings ), '%d' ) ) . ')',
					array_map( 'intval', $strings )
				)
			);

			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->prefix}icl_strings SET language=%s WHERE id IN (" . implode( ', ', array_fill( 0, count( $strings ), '%d' ) ) . ')',
					array_merge( array( $lang ), array_map( 'intval', $strings ) )
				)
			);

			$response['success'] = true;

			( new \WPML\ST\SameLanguageTranslations( $wpdb ) )->remove_for_strings( $strings, 'change-language-of-selected-strings' );

			foreach ( $strings as $string ) {
				icl_update_string_status( $string );
			}

			do_action(
				'wpml_st_language_of_strings_changed',
				$strings,
				array_values( array_unique( array_merge( array_map( 'strval', $previous_languages ), [ (string) $lang ] ) ) )
			);
		}

		return $response;
	}
}
