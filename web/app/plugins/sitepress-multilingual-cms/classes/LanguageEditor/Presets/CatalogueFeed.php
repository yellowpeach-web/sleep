<?php

namespace WPML\LanguageEditor\Presets;

class CatalogueFeed {

	const SOURCE_AMS = 'ams';

	const AUTHORITY_FULL = 'full';

	const AUTHORITY_DEGRADED = 'degraded';

	public $source;

	public $authority;

	public $version;

	public $count;

	public $checked;

	public $entries;

	public $unrecognized;

	public function __construct( $source, $authority, $version, $count, $checked, array $entries, array $unrecognized = [] ) {
		$this->source       = (string) $source;
		$this->authority    = (string) $authority;
		$this->version      = null === $version ? null : (int) $version;
		$this->count        = null === $count ? null : (int) $count;
		$this->checked      = (int) $checked;
		$this->entries      = $entries;
		$this->unrecognized = array_values( $unrecognized );
	}

	public function isFullAuthority() {
		return self::AUTHORITY_FULL === $this->authority;
	}
}
