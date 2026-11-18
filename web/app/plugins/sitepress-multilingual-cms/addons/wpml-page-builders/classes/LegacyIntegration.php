<?php

namespace WPML\PB;

use WPML\PB\Integrations\Divi\Helper;

class LegacyIntegration {

	public static function load() {
		global $sitepress;

		if ( defined( 'WPB_VC_VERSION' ) ) {
			$wpml_visual_composer = new \WPML_Compatibility_Plugin_Visual_Composer( new \WPML_Debug_BackTrace( null, 12 ) );
			$wpml_visual_composer->add_hooks();

			$wpml_visual_composer_grid = new \WPML_Compatibility_Plugin_Visual_Composer_Grid_Hooks(
				$sitepress,
				new \WPML_Translation_Element_Factory( $sitepress )
			);
			$wpml_visual_composer_grid->add_hooks();
		}

		if ( function_exists( 'avia_lang_setup' ) ) {
			$enfold = new \WPML_Compatibility_Theme_Enfold();
			$enfold->init_hooks();
		}

		$loader = new \WPML_Action_Filter_Loader();
		$loader->load( self::integrationClasses() );
	}

	public static function integrationClasses() {
		$integrationClasses = [
			\WPML\PB\FullSiteEditing\TemplateTranslationHooks::class,
			\WPML\PB\FullSiteEditing\TemplateFallbackHooks::class,
		];

		if ( defined( 'WPB_VC_VERSION' ) ) {
			$integrationClasses[] = \WPML\Compatibility\WPBakery\Hooks\TranslationJobLabels::class;
			$integrationClasses[] = \WPML\Compatibility\WPBakery\Hooks\TranslationJobImages::class;
			$integrationClasses[] = \WPML\Compatibility\WPBakery\Hooks\TranslationGuiLabels::class;
			$integrationClasses[] = \WPML\Compatibility\WPBakery\Hooks\Editor::class;
			$integrationClasses[] = \WPML\Compatibility\WPBakery\Hooks\RawHtml::class;
			$integrationClasses[] = \WPML\Compatibility\WPBakery\Hooks\DuplicateLinks::class;
			$integrationClasses[] = \WPML\Compatibility\WPBakery\Hooks\GutenbergUpdate::class;
			$integrationClasses[] = \WPML\Compatibility\WPBakery\Hooks\ImageLinksEncoding::class;
			$integrationClasses[] = \WPML\Compatibility\WPBakery\StylesFactory::class;
		}

		if ( defined( 'FUSION_BUILDER_VERSION' ) ) {
			$integrationClasses[] = \WPML_Compatibility_Plugin_Fusion_Hooks_Factory::class;
			$integrationClasses[] = \WPML\Compatibility\FusionBuilder\Frontend\Hooks::class;
			$integrationClasses[] = \WPML\Compatibility\FusionBuilder\Backend\Hooks::class;
			$integrationClasses[] = \WPML\Compatibility\FusionBuilder\DynamicContent::class;
			$integrationClasses[] = \WPML\Compatibility\FusionBuilder\FormContent::class;
			$integrationClasses[] = \WPML\Compatibility\FusionBuilder\FormNotifications::class;
			$integrationClasses[] = \WPML\Compatibility\FusionBuilder\PlainTextFallback::class;
			$integrationClasses[] = \WPML\Compatibility\FusionBuilder\Hooks\Editor::class;
			$integrationClasses[] = \WPML\Compatibility\FusionBuilder\Hooks\TranslationJobLabels::class;
			$integrationClasses[] = \WPML\Compatibility\FusionBuilder\Hooks\TranslationJobImages::class;
			$integrationClasses[] = \WPML\Compatibility\FusionBuilder\Hooks\TranslationGuiLabels::class;
			$integrationClasses[] = \WPML\Compatibility\FusionBuilder\Hooks\MultilingualOptions::class;
			$integrationClasses[] = \WPML\Compatibility\FusionBuilder\Hooks\MegaMenuSync::class;
			$integrationClasses[] = \WPML\Compatibility\FusionBuilder\Hooks\GlobalElementWriteBack::class;
			$integrationClasses[] = \WPML\Compatibility\FusionBuilder\Hooks\PortfolioRewriteRules::class;
			$integrationClasses[] = \WPML\Compatibility\FusionBuilder\Hooks\RawHtmlSetting::class;
			$integrationClasses[] = \WPML\Compatibility\FusionBuilder\Hooks\PostTranslationLinks::class;
		}

		if ( function_exists( 'avia_lang_setup' ) ) {
			$integrationClasses[] = \WPML\Compatibility\Enfold\Hooks\TranslationJobLabels::class;
			$integrationClasses[] = \WPML\Compatibility\Enfold\Hooks\TranslationJobImages::class;
			$integrationClasses[] = \WPML\Compatibility\Enfold\Hooks\TranslationGuiLabels::class;
		}

		if ( defined( 'ET_BUILDER_THEME' ) || defined( 'ET_BUILDER_PLUGIN_VERSION' ) ) {
			$integrationClasses[] = \WPML_Compatibility_Divi::class;
			$integrationClasses[] = \WPML\Compatibility\Divi\DynamicContent::class;
			$integrationClasses[] = \WPML\Compatibility\Divi\Search::class;
			$integrationClasses[] = \WPML\Compatibility\Divi\DiviOptionsEncoding::class;
			$integrationClasses[] = \WPML\Compatibility\Divi\ThemeBuilderFactory::class;
			$integrationClasses[] = \WPML\Compatibility\Divi\Builder::class;
			$integrationClasses[] = \WPML\Compatibility\Divi\TinyMCE::class;
			$integrationClasses[] = \WPML\Compatibility\Divi\DisplayConditions::class;
			$integrationClasses[] = \WPML\Compatibility\Divi\DoubleQuotes::class;
			$integrationClasses[] = \WPML\Compatibility\Divi\WooShortcodes::class;
			$integrationClasses[] = \WPML\Compatibility\Divi\Hooks\Editor::class;
			$integrationClasses[] = \WPML\Compatibility\Divi\Hooks\EditorFrontend::class;
			$integrationClasses[] = \WPML\Compatibility\Divi\Hooks\GutenbergUpdate::class;
			$integrationClasses[] = \WPML\Compatibility\Divi\Hooks\TranslationJobLabels::class;
			$integrationClasses[] = \WPML\Compatibility\Divi\Hooks\TranslationJobImages::class;
			$integrationClasses[] = \WPML\Compatibility\Divi\Hooks\TranslationGuiLabels::class;
			$integrationClasses[] = \WPML\Compatibility\Divi\Hooks\UnusedThemeBuilderLayouts::class;
			$integrationClasses[] = \WPML\Compatibility\Divi\Hooks\AppearedOnSite::class;
			$integrationClasses[] = \WPML\Compatibility\Divi\Hooks\ThemeBuilderOneList::class;
			$integrationClasses[] = \WPML\Compatibility\Divi\Hooks\ThemeBuilderRepairTool::class;
			$integrationClasses[] = \WPML\Compatibility\Divi\Hooks\RawHtmlSetting::class;
			$integrationClasses[] = \WPML\Compatibility\Divi\ConvertThemeOptions::class;
			$integrationClasses[] = \WPML\Compatibility\Divi\DynamicContent\Hooks::class;

			if ( Helper::isRunningDivi5() ) {
				$integrationClasses[] = \WPML\Compatibility\Divi\V5\DynamicContent::class;
				$integrationClasses[] = \WPML\Compatibility\Divi\V5\DisplayConditions::class;
				$integrationClasses[] = \WPML\Compatibility\Divi\V5\Editor::class;
				$integrationClasses[] = \WPML\Compatibility\Divi\V5\LanguageSwitcher::class;
				$integrationClasses[] = \WPML\Compatibility\Divi\V5\CanvasHooks::class;
				$integrationClasses[] = \WPML\Compatibility\Divi\V5\CopyContent::class;
				$integrationClasses[] = \WPML\Compatibility\Divi\V5\GlobalLayout::class;
				$integrationClasses[] = \WPML\Compatibility\Divi\V5\VisualBuilderGlobalLayout::class;
				$integrationClasses[] = \WPML\Compatibility\Divi\V5\GlobalLayoutStringsFactory::class;
				$integrationClasses[] = \WPML\Compatibility\Divi\V5\LocalCanvasStringsFactory::class;
				$integrationClasses[] = \WPML\Compatibility\Divi\V5\WooCommerce\ProductDescriptionCache::class;
			} else {
				$integrationClasses[] = \WPML\Compatibility\Divi\Hooks\DomainsBackendEditor::class;
			}
		}
		if ( defined( 'CS_VERSION' ) ) {
			$integrationClasses[] = \WPML\PB\Cornerstone\Hooks\TranslateEverything::class;
			$integrationClasses[] = \WPML\PB\Cornerstone\Hooks\SuppressFilters::class;
		}

		if ( function_exists( 'mfn_template_part_ID' ) ) {
			$integrationClasses[] = \WPML\PB\BeTheme\HooksFactory::class;
		}

		if ( defined( 'WOODMART_THEME_DIR' ) ) {
			$integrationClasses[] = \WPML\Compatibility\WoodMart\Hooks\LayoutFallback::class;
			$integrationClasses[] = \WPML\Compatibility\WoodMart\Hooks\LabelConditionIds::class;
			$integrationClasses[] = \WPML\Compatibility\WoodMart\Hooks\LayoutUnpublishedFallback::class;
		}

		return $integrationClasses;
	}
}
