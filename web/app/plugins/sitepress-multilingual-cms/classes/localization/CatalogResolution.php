<?php

namespace WPML\Localization;

class CatalogResolution {

	private $file;

	private $requested_locale;

	private $uses_fallback;

	private function __construct( $file, $requested_locale, $uses_fallback ) {
		$this->file             = $file;
		$this->requested_locale = $requested_locale;
		$this->uses_fallback    = $uses_fallback;
	}

	public static function unchanged( $file, $requested_locale = null ) {
		return new self( $file, $requested_locale, false );
	}

	public static function fallback( $file, $requested_locale ) {
		return new self( $file, $requested_locale, true );
	}

	public function file() {
		return $this->file;
	}

	public function requested_locale() {
		return $this->requested_locale;
	}

	public function uses_fallback() {
		return $this->uses_fallback;
	}
}
