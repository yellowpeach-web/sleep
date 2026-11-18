<?php

namespace WPML\Core\Component\UrlHandling\Domain\SubsiteClash;

final class SiteLanguageHosts {

	const FORMAT_DOMAINS = 2;

	private $isMultisite;

	private $hasSunrise;

	private $isWpmlActive;

	private $isSetupComplete;

	private $urlFormat;

	private $defaultLanguage;

	private $languageHosts;

	private $siteId;

	private $sitePath;

	public function __construct(
		bool $isMultisite,
		bool $hasSunrise,
		bool $isWpmlActive,
		bool $isSetupComplete,
		int $urlFormat,
		string $defaultLanguage,
		array $languageHosts,
		int $siteId,
		string $sitePath
	) {
		$this->isMultisite     = $isMultisite;
		$this->hasSunrise      = $hasSunrise;
		$this->isWpmlActive    = $isWpmlActive;
		$this->isSetupComplete = $isSetupComplete;
		$this->urlFormat       = $urlFormat;
		$this->defaultLanguage = $defaultLanguage;
		$this->languageHosts   = $languageHosts;
		$this->siteId          = $siteId;
		$this->sitePath        = $sitePath;
	}

	public static function outOfScope(): self {
		return new self( false, false, false, false, 0, '', [], 0, '/' );
	}

	public function isMultisite(): bool {
		return $this->isMultisite;
	}

	public function hasSunrise(): bool {
		return $this->hasSunrise;
	}

	public function isWpmlActive(): bool {
		return $this->isWpmlActive;
	}

	public function isSetupComplete(): bool {
		return $this->isSetupComplete;
	}

	public function urlFormat(): int {
		return $this->urlFormat;
	}

	public function defaultLanguage(): string {
		return $this->defaultLanguage;
	}

	public function languageHosts(): array {
		return $this->languageHosts;
	}

	public function siteId(): int {
		return $this->siteId;
	}

	public function sitePath(): string {
		return $this->sitePath;
	}
}
