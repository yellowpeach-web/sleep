<?php

class WPML_ST_Slug_Translation_UI_Factory {

	const POST          = 'post';
	const TAX           = 'taxonomy';
	const TEMPLATE_PATH = 'templates/slug-translation';

	public function create( $type ) {
		global $sitepress;

		$sync_settings_factory = new WPML_Element_Sync_Settings_Factory();
		$records_factory       = new WPML_Slug_Translation_Records_Factory();

		if ( WPML_Slug_Translation_Factory::POST === $type ) {
			$settings = new WPML_ST_Post_Slug_Translation_Settings( $sitepress );
		} elseif ( WPML_Slug_Translation_Factory::TAX === $type ) {
			$settings = new WPML_ST_Tax_Slug_Translation_Settings();
		} else {
			throw new Exception( 'Unknown element type.' );
		}

		$records          = $records_factory->create( $type );
		$sync             = $sync_settings_factory->create( $type );
		$template_loader  = new WPML_Twig_Template_Loader( array( WPML_ST_PATH . '/' . self::TEMPLATE_PATH ) );
		$template_service = $template_loader->get_template();
		$language_selector = new WPML_Simple_Language_Selector( $sitepress );

		$archive_records = WPML_Slug_Translation_Factory::POST === $type
			? $records_factory->create( WPML_Slug_Translation_Factory::POST_ARCHIVE )
			: null;

		$model = new WPML_ST_Element_Slug_Translation_UI_Model( $sitepress, $settings, $records, $sync, $language_selector, $archive_records );
		return new WPML_ST_Element_Slug_Translation_UI( $model, $template_service );
	}
}
