<?php

namespace ACFML;

use ACFML\Helper\ContentType;
use WPML\FP\Obj;

trait TranslationDataTrait {

	private $contentTypeHelper;

	private $hasTranslationType;

	public function __construct( ContentType $contentTypeHelper ) {
		$this->contentTypeHelper  = $contentTypeHelper;
		$this->hasTranslationType = ( null !== $this->contentTypeHelper->getWpmlSyncOptionKey() );
	}

	protected function getObjectTranslationType( $objectSlug ) {
		if ( $objectSlug ) {
			$settings = wpml_get_setting( $this->contentTypeHelper->getWpmlSyncOptionKey(), [] );
			return Obj::propOr( WPML_CONTENT_TYPE_DONT_TRANSLATE, $objectSlug, $settings );
		}

		return WPML_CONTENT_TYPE_DONT_TRANSLATE;
	}

	protected function getObjectTranslationContent( $objectSlug ) {
		switch ( $this->getObjectTranslationType( $objectSlug ) ) {
			case WPML_CONTENT_TYPE_DONT_TRANSLATE:
				/* translators: Status shown in the Multilingual Setup panel for a post type or taxonomy WPML does not translate. Adjective. */
				return __( 'Not translatable', 'acfml' );
			case WPML_CONTENT_TYPE_TRANSLATE:
			case WPML_CONTENT_TYPE_DISPLAY_AS_IF_TRANSLATED:
				/* translators: Status shown in the Multilingual Setup panel for a post type or taxonomy WPML translates. Adjective. */
				return __( 'Translatable', 'acfml' );
		}
		return '';
	}

	protected function getObjectTranslationContext( $objectSlug ) {
		switch ( $this->getObjectTranslationType( $objectSlug ) ) {
			case WPML_CONTENT_TYPE_TRANSLATE:
				/* translators: Second half of a status line in the Multilingual Setup panel; it follows "Translatable" and a dash. Lower case for that reason. */
				return __( 'only show translated items', 'acfml' );
			case WPML_CONTENT_TYPE_DISPLAY_AS_IF_TRANSLATED:
				/* translators: Second half of a status line in the Multilingual Setup panel; it follows "Translatable" and a dash. Lower case for that reason. */
				return __( 'use translation if available or fallback to default language', 'acfml' );
		}
		return '';
	}

	protected function getObjectTranslationInformation( $objectSlug, $separator = '' ) {
		$content = $this->getObjectTranslationContent( $objectSlug );
		$context = $this->getObjectTranslationContext( $objectSlug );

		return $this->getTranslationInformation( $content, $context, $separator );
	}

	protected function getTranslationInformation( $content, $context = '', $separator = '' ) {
		$information = '<span class="acfml-translation-info">' . esc_html( $content );
		if ( $context ) {
			$information .= $separator . '<span class="acfml-translation-info-context">' . esc_html( $context ) . '</span>';
		}
		$information .= '</span>';

		return $information;
	}

}
