<?php

namespace WPML\Core\Component\UrlHandling\Domain\SubsiteClash;

final class MainSiteUrlState {

	const FORMAT_DIRECTORIES = 1;

	private $isMultisite;

	private $isSubdomainInstall;

	private $wpmlActive;

	private $setupComplete;

	private $urlFormat;

	private $directoryForDefaultLanguage;

	private $defaultLanguage;

	private $languages;

	private $mainSiteId;

	private $domain;

	private $path;

	public function __construct(
		bool $isMultisite,
		bool $isSubdomainInstall,
		bool $wpmlActive,
		bool $setupComplete,
		int $urlFormat,
		bool $directoryForDefaultLanguage,
		string $defaultLanguage,
		array $languages,
		int $mainSiteId,
		string $domain,
		string $path
	) {
		$this->isMultisite                 = $isMultisite;
		$this->isSubdomainInstall          = $isSubdomainInstall;
		$this->wpmlActive                  = $wpmlActive;
		$this->setupComplete               = $setupComplete;
		$this->urlFormat                   = $urlFormat;
		$this->directoryForDefaultLanguage = $directoryForDefaultLanguage;
		$this->defaultLanguage             = $defaultLanguage;
		$this->languages                   = array_values( $languages );
		$this->mainSiteId                  = $mainSiteId;
		$this->domain                      = $domain;
		$this->path                        = $path;
	}

	public static function withoutSubdirectoryNetwork( bool $isMultisite, bool $isSubdomainInstall ): self {
		return new self( $isMultisite, $isSubdomainInstall, false, false, self::FORMAT_DIRECTORIES, false, '', [], 0, '', '/' );
	}

	public function forSetupWizard(): self {
		$state                = clone $this;
		$state->setupComplete = true;

		return $state;
	}

	public function isMultisite(): bool {
		return $this->isMultisite;
	}

	public function isSubdomainInstall(): bool {
		return $this->isSubdomainInstall;
	}

	public function isWpmlActive(): bool {
		return $this->wpmlActive;
	}

	public function isSetupComplete(): bool {
		return $this->setupComplete;
	}

	public function urlFormat(): int {
		return $this->urlFormat;
	}

	public function usesDirectoryForDefaultLanguage(): bool {
		return $this->directoryForDefaultLanguage;
	}

	public function defaultLanguage(): string {
		return $this->defaultLanguage;
	}

	public function languages(): array {
		return $this->languages;
	}

	public function mainSiteId(): int {
		return $this->mainSiteId;
	}

	public function domain(): string {
		return $this->domain;
	}

	public function path(): string {
		$path = trim( $this->path, '/' );

		return '' === $path ? '/' : '/' . $path . '/';
	}
}
