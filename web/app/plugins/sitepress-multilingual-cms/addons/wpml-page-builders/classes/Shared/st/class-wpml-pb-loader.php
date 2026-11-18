<?php

use WPML\PB\Container\Config;
use function WPML\Container\make;
use function WPML\Container\share;

class WPML_PB_Loader {

	public function __construct(
		WPML_ST_Settings $st_settings,
		$pb_integration = null
	) {
		share( Config::getSharedClasses() );

		do_action( 'wpml_load_page_builders_integration' );

		$page_builder_strategies = array();

		$required = apply_filters( 'wpml_page_builder_support_required', array() );
		foreach ( $required as $plugin ) {
			$page_builder_strategies[] = new WPML_PB_API_Hooks_Strategy( $plugin );
		}

		$page_builder_config_import = new WPML_PB_Config_Import_Shortcode( $st_settings );
		$page_builder_config_import->add_hooks();
		if ( $page_builder_config_import->has_settings() ) {
			$strategy = new WPML_PB_Shortcode_Strategy( new WPML_Page_Builder_Settings() );
			$strategy->set_shortcodes_provider(
				function () use ( $page_builder_config_import ) {
					$settings = (array) $page_builder_config_import->get_settings();

					if ( defined( 'FUSION_BUILDER_VERSION' ) ) {
						$settings = \WPML\Compatibility\FusionBuilder\DynamicParamsEncoding::adjustStringSettings( $settings );
					}

					return defined( 'WPB_VC_VERSION' )
						? \WPML\Compatibility\WPBakery\ImageLinks::addStringSettings(
							\WPML\Compatibility\WPBakery\SingleImageLink::addStringSettings( $settings )
						)
						: $settings;
				}
			);
			$page_builder_strategies[] = $strategy;

			add_filter(
				'wpml_pb_shortcode_tags',
				function ( $tags ) use ( $strategy ) {
					return array_merge( (array) $tags, $strategy->get_shortcodes() );
				}
			);

			( new \WPML\PB\Duplication\ShortcodeLinksHooks( $strategy, $GLOBALS['wpdb'], make( \WPML\PB\Duplication\TargetLanguageLinks::class ) ) )->add_hooks();

			add_filter(
				'wpml_pb_element_uid_markup_providers',
				function ( $providers ) use ( $strategy ) {
					$providers[] = new \WPML\PB\ElementUid\Markup\ShortcodeProvider( $strategy, new WPML_PB_Shortcode_Encoding() );

					return $providers;
				}
			);

			$shortcodes_media_update_factory     = new WPML_Page_Builders_Media_Shortcodes_Update_Factory( $page_builder_config_import );
			$is_media_translation_plugin_enabled = defined( 'WPML_MEDIA_VERSION' ) && $shortcodes_media_update_factory->get_media_settings();

			$shortcodes_media_hooks = new WPML_Page_Builders_Media_Hooks(
				$shortcodes_media_update_factory,
				'shortcodes'
			);
			$shortcodes_media_hooks->add_hooks( $is_media_translation_plugin_enabled );
		}

		self::load_hooks();

		if ( $page_builder_strategies ) {
			if ( $pb_integration ) {
				$factory = $pb_integration->get_factory();
			} else {
				$factory        = make( 'WPML_PB_Factory' );
				$pb_integration = make( 'WPML_PB_Integration' );
			}
			$pb_integration->add_hooks();
			foreach ( $page_builder_strategies as $strategy ) {
				$strategy->set_factory( $factory );
				$pb_integration->add_strategy( $strategy );
			}
		}
	}

	private static function load_hooks() {
		$hooks = [
			WPML_PB_Handle_Post_Body::class,
			WPML\PB\AutoUpdate\Hooks::class,
			WPML\PB\Shutdown\Hooks::class,
			WPML\PB\ElementUid\Hooks::class,
			WPML\PB\GutenbergCleanup\ShortcodeHooks::class,
			WPML\PB\Shortcode\AdjustIdsHooks::class,
			WPML\PB\Strings\RegisterHooks::class,
			WPML\PB\Media\Hooks::class,
			WPML\PB\AnchorLinks\Hooks::class,
		];

		if ( defined( 'FUSION_BUILDER_VERSION' ) ) {
			$hooks[] = WPML\Compatibility\FusionBuilder\Hooks\LivePreviewIds::class;
		}

		make( WPML_Action_Filter_Loader::class )->load( $hooks );
	}
}
