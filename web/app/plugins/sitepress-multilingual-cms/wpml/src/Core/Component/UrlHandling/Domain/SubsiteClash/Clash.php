<?php

namespace WPML\Core\Component\UrlHandling\Domain\SubsiteClash;

final class Clash implements LanguageUrlClash {

	private $language;

	private $directoryCode;

	private $site;

	public function __construct( ActiveLanguage $language, string $directoryCode, SitePath $site ) {
		$this->language      = $language;
		$this->directoryCode = $directoryCode;
		$this->site          = $site;
	}

	public function address(): string {
		return '/' . $this->directoryCode . '/';
	}

	public function language(): ActiveLanguage {
		return $this->language;
	}

	public function languageCode(): string {
		return $this->language->code();
	}

	public function languageName(): string {
		return $this->language->displayName();
	}

	public function isHiddenLanguage(): bool {
		return $this->language->isHidden();
	}

	public function directoryCode(): string {
		return $this->directoryCode;
	}

	public function site(): SitePath {
		return $this->site;
	}

	public function siteId(): int {
		return $this->site->id();
	}

	public function siteName(): string {
		return $this->site->name();
	}

	public function siteUrl(): string {
		return $this->site->url();
	}

	public function statuses(): array {
		return $this->site->statuses();
	}

	public function withSiteName( string $name ): self {
		return new self( $this->language, $this->directoryCode, $this->site->withName( $name ) );
	}
}
