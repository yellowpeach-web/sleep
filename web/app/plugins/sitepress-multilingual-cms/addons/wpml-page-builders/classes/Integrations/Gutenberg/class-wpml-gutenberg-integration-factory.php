<?php

use function WPML\Container\make;
use function WPML\Container\share;

class WPML_Gutenberg_Integration_Factory {

	private $strings_in_block;

	public function create() {
		global $sitepress;

		$integrations = new WPML\PB\Gutenberg\Integration_Composite();

		$mainIntegration = $this->create_gutenberg_integration();
		share( [ $mainIntegration ] );

		$integrations->add( $mainIntegration );

		$integrations->add( new \WPML\PB\Gutenberg\BlockUid\Hooks( $this->strings_in_block, null, $sitepress ) );

		$integrations->add(
			new \WPML\PB\Gutenberg\NativeEditorWriteBack(
				make( \WPML_PB_Integration::class ),
				$this->strings_in_block,
				$sitepress,
				new \WPML\PB\Gutenberg\NativeEditorJobFields( $sitepress ),
				new \WPML\PB\Gutenberg\NativeEditorStringTranslations()
			)
		);

		if ( $this->should_translate_reusable_blocks() ) {
			$integrations->add(
				make( '\WPML\PB\Gutenberg\ReusableBlocks\Integration' )
			);
		}

		if ( ! is_admin() ) {
			$integrations->add(
				make( \WPML\PB\Gutenberg\Widgets\Block\DisplayTranslation::class )
			);
			$integrations->add(
				make( \WPML\PB\Gutenberg\Widgets\Block\Search::class )
			);
			$integrations->add(
				make( \WPML\PB\Gutenberg\Navigation\Frontend::class )
			);
			$integrations->add(
				make( \WPML\PB\Gutenberg\ConvertIdsInBlock\Hooks::class )
			);
		}

		$integrations->add(
			make( \WPML\PB\Gutenberg\Widgets\Block\RegisterStrings::class )
		);

		$integrations->add(
			make( \WPML\PB\Gutenberg\Widgets\Block\RegisterPackageKind::class )
		);

		$integrations->add(
			make( \WPML\PB\Gutenberg\Hooks\TranslationJobImages::class )
		);

		$integrations->add(
			make( \WPML\PB\Gutenberg\Hooks\TranslationGuiLabels::class )
		);

		$integrations->add(
			make( \WPML\PB\Gutenberg\Hooks\BlockDefaultStrings::class )
		);

		$integrations->add(
			new \WPML\PB\Gutenberg\MediaHooksIntegration( $mainIntegration->get_config_option() )
		);

		return $integrations;
	}

	public function create_gutenberg_integration() {
		global $sitepress, $wpdb;

		$config_option    = new WPML_Gutenberg_Config_Option();
		$strings_in_block = self::createStringsInBlock( $config_option );
		$string_factory   = new WPML_ST_String_Factory( $wpdb );

		$this->strings_in_block = $strings_in_block;

		$strings_registration = new WPML_Gutenberg_Strings_Registration(
			$strings_in_block,
			$string_factory,
			new WPML_PB_Reuse_Translations(
				$string_factory,
				[ new WPML\PB\Gutenberg\StringsInBlock\DOMHandler\HtmlBlock(), 'getPreviousRegistrationForms' ]
			),
			new WPML_PB_String_Translation( $wpdb ),
			make( 'WPML_Translate_Link_Targets' ),
			WPML\PB\TranslateLinks::getTranslatorForString( $string_factory, $sitepress->get_active_languages() )
		);

		return new WPML_Gutenberg_Integration(
			$strings_in_block,
			$config_option,
			$strings_registration,
			$sitepress
		);
	}

	public static function createStringsInBlock( WPML_Gutenberg_Config_Option $config_option ) {
		$string_parsers = [
			new WPML\PB\Gutenberg\StringsInBlock\HTML( $config_option ),
			new WPML\PB\Gutenberg\StringsInBlock\Attributes( $config_option ),
			new WPML\PB\Gutenberg\StringsInBlock\AttributesFallback( $config_option ),
			new WPML\PB\Gutenberg\StringsInBlock\MoreBlock( $config_option ),
		];

		return new WPML\PB\Gutenberg\StringsInBlock\Collection( $string_parsers );
	}

	private function should_translate_reusable_blocks() {
		global $sitepress;

		return $sitepress->is_translated_post_type(
			WPML\PB\Gutenberg\ReusableBlocks\Translation::POST_TYPE
		);
	}
}
