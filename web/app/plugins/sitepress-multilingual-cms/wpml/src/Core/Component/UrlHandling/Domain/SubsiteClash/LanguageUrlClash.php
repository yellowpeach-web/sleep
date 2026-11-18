<?php

namespace WPML\Core\Component\UrlHandling\Domain\SubsiteClash;

interface LanguageUrlClash {

	public function address(): string;

	public function languageCode(): string;

	public function languageName(): string;

	public function isHiddenLanguage(): bool;

	public function site(): SitePath;

	public function siteId(): int;

	public function siteName(): string;

	public function siteUrl(): string;

	public function statuses(): array;

	public function withSiteName( string $name );
}
