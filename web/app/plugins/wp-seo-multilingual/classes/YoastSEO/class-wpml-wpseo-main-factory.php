<?php

class WPML_WPSEO_Main_Factory implements IWPML_Backend_Action_Loader, IWPML_Frontend_Action_Loader, IWPML_AJAX_Action_Loader {

	public function create() {
		global $sitepress, $wpml_url_converter;

		$canonicals = new WPML_Canonicals( $sitepress, new WPML_Translation_Element_Factory( $sitepress ) );

		$hooks                           = array();
		$hooks['xml-sitemap']            = new WPML_WPSEO_XML_Sitemaps_Filter( $sitepress, $wpml_url_converter, new WPSEO_Sitemap_Image_Parser() );
		$hooks['filters']                = new WPML_WPSEO_Filters( $canonicals );
		$hooks['categories']             = new WPML_WPSEO_Categories( $this->getSlugTranslationSettingsFactory() );
		$hooks['should-create-redirect'] = new WPML_WPSEO_Should_Create_Redirect();

		return $hooks;
	}

	private function getSlugTranslationSettingsFactory() {
		return class_exists( \WPML_ST_Slug_Translation_Settings_Factory::class ) ? new \WPML_ST_Slug_Translation_Settings_Factory() : null;
	}
}
