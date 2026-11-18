<?php

namespace WPML\Core\Component\UrlHandling\Domain\SubsiteClash;

final class LanguageHost {

	private $language;

	private $host;

	public function __construct( ActiveLanguage $language, string $host ) {
		$this->language = $language;
		$this->host     = $host;
	}

	public function language(): ActiveLanguage {
		return $this->language;
	}

	public function host(): string {
		return $this->host;
	}
}
