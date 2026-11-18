<?php

namespace WPML\BlockEditor\Blocks\LanguageSwitcher\Model\Label;

use WPML\BlockEditor\Blocks\LanguageSwitcher\Model\LanguageItem;

class BothLanguages implements LabelTemplateInterface {
	const XPATH_LOCATOR = '//*[@data-wpml-label-type="both"]';

	public function matchesXPath( \DOMXPath $domXPath, $prefix ) {
		return $domXPath->query( $prefix . self::XPATH_LOCATOR )->length > 0;
	}

	public function getDisplayName( LanguageItem $languageItem ) {
		return $languageItem->getNativeName() === $languageItem->getDisplayName()
			? $languageItem->getDisplayName()
			: sprintf( '%s (%s)', $languageItem->getNativeName(), $languageItem->getDisplayName() );
	}

	public function writeLabel( \DOMElement $label, LanguageItem $languageItem ) {
		if ( $languageItem->getNativeName() === $languageItem->getDisplayName() || ! $label->ownerDocument ) {
			( new NativeLanguage() )->writeLabel( $label, $languageItem );
			return;
		}

		$document           = $label->ownerDocument;
		$label->textContent = '';

		$native = $document->createElement( 'span' );
		$native->appendChild( $document->createTextNode( $languageItem->getNativeName() ) );
		NativeLanguage::markLanguage( $native, $languageItem );

		$label->appendChild( $native );
		$label->appendChild( $document->createTextNode( sprintf( ' (%s)', $languageItem->getDisplayName() ) ) );
	}
}
