<?php

namespace WPML\Core\Component\UrlHandling\Domain\SubsiteClash;

final class ActiveLanguage {

	private $code;

	private $urlCode;

	private $displayName;

	private $hidden;

	public function __construct( string $code, string $urlCode, string $displayName, bool $hidden ) {
		$this->code        = $code;
		$this->urlCode     = $urlCode;
		$this->displayName = $displayName;
		$this->hidden      = $hidden;
	}

	public function code(): string {
		return $this->code;
	}

	public function urlCode(): string {
		return $this->urlCode;
	}

	public function displayName(): string {
		return '' === $this->displayName ? $this->code : $this->displayName;
	}

	public function isHidden(): bool {
		return $this->hidden;
	}
}
