<?php
namespace WPML\BlockEditor\Blocks\LanguageSwitcher\Model;

class LanguageItem {

	private $displayName;

	private $nativeName;

	private $code;

	private $url;

	private $flagUrl;

	private $flagTitle;

	private $flagAlt;

	private $hreflang;

	public function __construct( $displayName, $nativeName, $code, $url, $flagUrl, $flagTitle, $flagAlt, $hreflang = '' ) {
		$this->displayName = $displayName;
		$this->nativeName = $nativeName;
		$this->code = $code;
		$this->url = $url;
		$this->flagUrl = $flagUrl;
		$this->flagTitle = $flagTitle;
		$this->flagAlt = $flagAlt;
		$this->hreflang = (string) $hreflang;
	}

	public function getDisplayName() {
		return $this->displayName;
	}

	public function getCode() {
		return $this->code;
	}

	public function getUrl() {
		return $this->url;
	}

	public function getFlagUrl() {
		return $this->flagUrl;
	}

	public function getNativeName() {
		return $this->nativeName;
	}

	public function getFlagTitle() {
		return $this->flagTitle;
	}

	public function getFlagAlt() {
		return $this->flagAlt;
	}

	public function getHreflang() {
		return '' !== $this->hreflang ? $this->hreflang : $this->code;
	}

}