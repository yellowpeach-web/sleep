<?php

namespace WPML\PB\BeaverBuilder\Hooks;

use WPML\FP\Obj;
use WPML\PB\Links\PostDataLinks;
use WPML\PB\Request\Ajax;
use WPML_Beaver_Builder_Data_Settings;

class CopyFromOriginal implements \IWPML_Action {

	const BEAVER_COPY_ACTION = 'fl_builder_duplicate_wpml_layout';

	const BEAVER_COPY_NONCE_ACTION = 'fl_wpml_duplicate_clicked';

	const PRIORITY_AFTER_BEAVER_COPY = 11;

	private $dataSettings;

	private $postDataLinks;

	public function __construct( WPML_Beaver_Builder_Data_Settings $dataSettings, PostDataLinks $postDataLinks ) {
		$this->dataSettings  = $dataSettings;
		$this->postDataLinks = $postDataLinks;
	}

	public function add_hooks() {
		add_action( 'admin_footer-post-new.php', [ $this, 'printOriginalIdForBeaverCopy' ] );
		Ajax::listen(
			self::BEAVER_COPY_ACTION,
			[ $this, 'convertCopiedLayoutLinks' ],
			'Beaver copy of the original layout: converts the links of the layout Beaver just copied into the translation; checks Beaver\'s nonce and edit_post on that translation',
			self::PRIORITY_AFTER_BEAVER_COPY
		);
	}

	public function printOriginalIdForBeaverCopy() {
		$originalId = $this->getBeaverOriginalOfNewTranslation();

		if ( $originalId ) {
			printf( '<input type="hidden" id="icl_translation_of" value="%s" />', esc_attr( (string) $originalId ) );
		}
	}

	public function convertCopiedLayoutLinks() {
		if ( ! check_ajax_referer( self::BEAVER_COPY_NONCE_ACTION, false, false ) ) {
			return;
		}

		$postId = (int) Obj::prop( 'post_id', $_POST );

		if (
			! $postId
			|| ! current_user_can( 'edit_post', $postId )
			|| ! $this->dataSettings->is_handling_post( $postId )
		) {
			return;
		}

		$language = $this->getTranslationLanguage( $postId );

		if ( $language ) {
			$this->postDataLinks->convert( $postId, $language );
		}
	}

	private function getTranslationLanguage( $postId ) {
		$language = apply_filters(
			'wpml_element_language_code',
			null,
			[
				'element_id'   => $postId,
				'element_type' => get_post_type( $postId ),
			]
		);

		return $language ?: $this->getLanguageOfReferringScreen();
	}

	private function getLanguageOfReferringScreen() {
		parse_str( (string) wp_parse_url( (string) wp_get_referer(), PHP_URL_QUERY ), $query );
		$language = (string) Obj::prop( 'lang', $query );

		return $language && apply_filters( 'wpml_language_is_active', false, $language ) ? $language : '';
	}

	private function getBeaverOriginalOfNewTranslation() {
		$trid = (int) Obj::prop( 'trid', $_GET );

		if ( ! $trid || 'auto-draft' !== get_post_status() ) {
			return 0;
		}

		$originalId = $this->getOriginalId( $trid, 'post_' . get_post_type() );

		return $originalId && $this->dataSettings->is_handling_post( $originalId ) ? $originalId : 0;
	}

	private function getOriginalId( $trid, $elementType ) {
		$translations = (array) apply_filters( 'wpml_get_element_translations', null, $trid, $elementType );

		foreach ( $translations as $translation ) {
			if ( ! empty( $translation->original ) ) {
				return (int) $translation->element_id;
			}
		}

		return 0;
	}
}
