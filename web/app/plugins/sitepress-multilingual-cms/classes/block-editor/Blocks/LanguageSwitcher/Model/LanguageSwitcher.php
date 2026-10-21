<?php
namespace WPML\BlockEditor\Blocks\LanguageSwitcher\Model;

use WPML\FP\Fns;
use WPML\FP\Lst;
use WPML\FP\Relation;
use function WPML\FP\invoke;
use function WPML\FP\pipe;

class LanguageSwitcher {

	private $languageItems;

	private $currentLanguageCode;

	private $hasAlternatives;

	public function __construct( $currentLanguageCode, array $languageItems, $hasAlternatives = null )
	{
		$this->currentLanguageCode = $currentLanguageCode;
		$this->languageItems = $languageItems;
		$this->hasAlternatives = null === $hasAlternatives ? null : (bool) $hasAlternatives;
	}

	public function getLanguageItems() {
		return $this->languageItems;
	}

	public function getCurrentLanguageItem() {
		return Lst::find(pipe(invoke('getCode'), Relation::equals($this->currentLanguageCode)), $this->languageItems);
	}

	public function getAlternativeLanguageItems() {
		return Fns::reject(pipe(invoke('getCode'), Relation::equals($this->currentLanguageCode)), $this->languageItems);
	}

	public function hasAlternatives() {
		return null === $this->hasAlternatives
			? [] !== $this->getAlternativeLanguageItems()
			: $this->hasAlternatives;
	}

	public function getCurrentLanguageCode() {
		return $this->currentLanguageCode;
	}
}