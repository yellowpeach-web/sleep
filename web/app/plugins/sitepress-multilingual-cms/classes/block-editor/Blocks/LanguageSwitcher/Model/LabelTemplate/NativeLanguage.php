<?php
namespace WPML\BlockEditor\Blocks\LanguageSwitcher\Model\Label;

use WPML\BlockEditor\Blocks\LanguageSwitcher\Model\LanguageItem;

class NativeLanguage implements LabelTemplateInterface {
	const XPATH_LOCATOR    = '//*[@data-wpml-label-type="native"]';
	public function matchesXPath( \DOMXPath $domXPath, $prefix ) {
		return $domXPath->query( $prefix . self::XPATH_LOCATOR )->length > 0;
	}
	public function getDisplayName( LanguageItem $languageItem ) {
		return $languageItem->getNativeName();
	}

	public function writeLabel( \DOMElement $label, LanguageItem $languageItem ) {
		$label->textContent = $this->getDisplayName( $languageItem );
		self::markLanguage( $label, $languageItem );
	}

	public static function markLanguage( \DOMElement $element, LanguageItem $languageItem ) {
		$tag = $languageItem->getHreflang();
		if ( '' !== $tag ) {
			$element->setAttribute( 'lang', $tag );
		}
	}
}
