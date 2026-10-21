<?php

namespace WPML\TM\Settings;

class TermMetaCopy {

	private $sitepress;

	public function __construct( \SitePress $sitepress ) {
		$this->sitepress = $sitepress;
	}

	public function isTermField( string $name ): bool {
		$settings = (array) $this->sitepress->core_tm()->settings;

		return isset( $settings[ WPML_TERM_META_SETTING_INDEX_PLURAL ][ $name ] );
	}

	public function copyToTranslations( int $termTaxonomyId, array $metaKeys, bool $onlyIntoEmpty ) {
		( new \WPML_Sync_Term_Meta_Action( $this->sitepress, $termTaxonomyId ) )->copy_keys_to_translations( $metaKeys, $onlyIntoEmpty );
	}
}
