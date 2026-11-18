<?php

use WPML\PB\BeaverBuilder\NodesWithKeptStrings;
use function WPML\Container\make;

class WPML_Beaver_Builder_Integration_Factory {

	const SLUG = 'beaver-builder';

	public function create() {
		$action_filter_loader = new WPML_Action_Filter_Loader();
		$action_filter_loader->load(
			array(
				'WPML_PB_Beaver_Builder_Handle_Custom_Fields_Factory',
				'WPML_Beaver_Builder_Media_Hooks_Factory',
				\WPML\PB\BeaverBuilder\TranslationJob\Hooks::class,
				\WPML\PB\BeaverBuilder\TranslationJob\HiddenFields::class,
				\WPML\PB\BeaverBuilder\Config\Factory::class,
				\WPML\PB\BeaverBuilder\Hooks\Editor::class,
				\WPML\PB\BeaverBuilder\Hooks\Menu::class,
				\WPML\PB\BeaverBuilder\Hooks\Cache::class,
				\WPML\PB\BeaverBuilder\Hooks\TranslationGuiLabels::class,
				\WPML\PB\BeaverBuilder\Hooks\TranslationJobImages::class,
				\WPML\PB\BeaverBuilder\Hooks\Patterns::class,
				\WPML\PB\BeaverBuilder\Hooks\SavedItems::class,
				\WPML\PB\BeaverBuilder\Hooks\RawHtmlSetting::class,

				\WPML\PB\BeaverBuilder\BeaverThemer\HooksFactory::class,
			)
		);

		$nodes         = new WPML_Beaver_Builder_Translatable_Nodes();
		$data_settings = new WPML_Beaver_Builder_Data_Settings();

		$post_data_links = new \WPML\PB\Links\PostDataLinks( $data_settings );
		( new \WPML\PB\Duplication\Hooks( $data_settings, $post_data_links ) )->add_hooks();
		( new \WPML\PB\BeaverBuilder\Hooks\CopyFromOriginal( $data_settings, $post_data_links ) )->add_hooks();

		$string_registration_factory = new WPML_String_Registration_Factory( $data_settings->get_pb_name() );
		$string_registration         = $string_registration_factory->create();

		$factory  = make( WPML_PB_Factory::class );
		$strategy = make( WPML_PB_API_Hooks_Strategy::class, [ ':name' => $data_settings->get_pb_name() ] );
		$strategy->set_factory( $factory );

		$reuse_translation = make( WPML_PB_Reuse_Translations_By_Strategy::class, [ ':strategy' => $strategy ] );

		$register_strings   = new WPML_Beaver_Builder_Register_Strings( new NodesWithKeptStrings( $nodes ), $data_settings, $string_registration, $reuse_translation );
		$update_translation = new WPML_Beaver_Builder_Update_Translation( $nodes, $data_settings );

		return new WPML_Page_Builders_Integration( $register_strings, $update_translation, $data_settings );
	}
}
