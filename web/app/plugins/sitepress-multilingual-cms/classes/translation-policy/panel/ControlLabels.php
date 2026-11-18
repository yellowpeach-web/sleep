<?php

namespace WPML\TranslationPolicy\Panel;

use WPML\TranslationPolicy\Policy\PolicyTable;

class ControlLabels {

	const PRIORITY = \WPML_TM_Translation_Status_Display::STATUS_TEXT_PRIORITY - 5;

	private $translatesAutomatically;

	private $originalOf;

	private $answers = [];

	private $languageName;

	public function __construct( callable $translatesAutomatically, callable $originalOf, callable $languageName ) {
		$this->translatesAutomatically = $translatesAutomatically;
		$this->originalOf              = $originalOf;
		$this->languageName            = $languageName;
	}

	public function filterText( $text, $postId, $language, $trid, $cssClass = '' ) {
		$label = $this->label( (string) $cssClass, (int) $postId, (int) $trid, (string) $language );

		return null === $label ? $text : $label;
	}

	public function label( string $cssClass, int $postId, int $trid, string $language ) {
		$isAdd = \WPML_Post_Status_Display::ICON_TRANSLATION_ADD === $cssClass;
		if ( ! $postId || ! $trid || '' === $language || ( ! $isAdd && \WPML_Post_Status_Display::ICON_TRANSLATION_NEEDS_UPDATE !== $cssClass ) ) {
			return null;
		}

		$name = (string) call_user_func( $this->languageName, $language );
		if ( '' === $name ) {
			return null;
		}

		$key = $trid . '|' . $language;
		if ( ! isset( $this->answers[ $key ] ) ) {
			$originalId = (int) call_user_func( $this->originalOf, $postId );
			if ( ! $originalId ) {
				return null;
			}
			$this->answers[ $key ] = (bool) call_user_func( $this->translatesAutomatically, $trid, $originalId, $language );
		}

		$method = $this->answers[ $key ] ? PolicyTable::METHOD_PTC : PolicyTable::METHOD_SELF;

		return $isAdd ? Words::translateTo( $method, $name ) : Words::updateTranslation( $method, $name );
	}
}
