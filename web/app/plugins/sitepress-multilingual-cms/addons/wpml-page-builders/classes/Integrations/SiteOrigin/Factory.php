<?php

namespace WPML\PB\SiteOrigin;

class Factory {

	const SLUG = 'siteorigin';

	public function create() {
		global $wpdb;

		$loader = new \WPML_Action_Filter_Loader();
		$loader->load(
			[
				HandleCustomFieldsFactory::class,
				Media\HooksFactory::class,
				Config\Factory::class,
				Hooks\Editor::class,
				Hooks\TranslationJobImages::class,
				Hooks\CloneLayouts::class,
				Hooks\WidgetsCache::class,
				Hooks\LayoutBlockNestedLayoutsFactory::class,
			]
		);

		$nodes        = new TranslatableNodes();
		$dataSettings = new DataSettings();

		( new Hooks\Duplication( $dataSettings ) )->add_hooks();
		$positionStringNames = new PositionStringNames( $nodes );

		( new Hooks\StringNameMigration(
			$wpdb,
			$nodes,
			$dataSettings,
			$positionStringNames,
			new \WPML_WP_Cache( \WPML_Register_String_Filter::CACHE_GROUP )
		) )->add_hooks();
		( new \WPML\PB\Duplication\Hooks( $dataSettings, new \WPML\PB\Links\PostDataLinks( $dataSettings ) ) )->add_hooks();

		$stringRegistrationFactory = new \WPML_String_Registration_Factory( $dataSettings->get_pb_name() );
		$stringRegistration        = $stringRegistrationFactory->create();

		return new \WPML_Page_Builders_Integration(
			new RegisterStrings( $nodes, $dataSettings, $stringRegistration ),
			new UpdateTranslation( $nodes, $dataSettings, $positionStringNames ),
			$dataSettings
		);
	}
}
