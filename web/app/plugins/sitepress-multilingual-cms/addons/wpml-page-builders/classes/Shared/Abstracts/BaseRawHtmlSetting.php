<?php

namespace WPML\Compatibility;

abstract class BaseRawHtmlSetting implements \IWPML_Frontend_Action, \IWPML_Backend_Action {

	private $settings;

	public function __construct( ?\WPML_Page_Builder_Settings $settings = null ) {
		$this->settings = $settings ?: new \WPML_Page_Builder_Settings();
	}

	protected function isRawHtmlTranslatable(): bool {
		return $this->settings->is_raw_html_translatable();
	}
}
