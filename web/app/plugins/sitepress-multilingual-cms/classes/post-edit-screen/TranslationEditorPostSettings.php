<?php

namespace WPML\TM\PostEditScreen;

use WPML\Element\API\PostTranslations;
use WPML\Element\API\Translations;
use WPML\FP\Fns;
use WPML\LIB\WP\Hooks;
use WPML\TM\PostEditScreen\Endpoints\GetPtcEngineStatus;
use WPML\TM\PostEditScreen\Endpoints\SetEditorMode;
use WPML\Core\WP\App\Resources;
use WPML\Core\Component\Post\Application\LanguagePanel\SiteEditorEntityTypes;
use WPML\API\Settings;
use function WPML\FP\spreadArgs;
use WPML\PostHog\Event\SendDataToPostHog;
use WPML\PostHog\State\PostHogState;

class TranslationEditorPostSettings {
	private $sitepress;

	private $hooked = false;

	public function __construct( $sitepress ) {
		$this->sitepress = $sitepress;
		$this->registerPostHogAjaxCapture();
	}

	public function add_hooks() {
		Hooks::onAction( 'admin_enqueue_scripts' )
		     ->then( [ $this, 'enqueueClassicApp' ] );

		$this->addRenderHooks();
	}

	public function addToPluginScreen() {
		if ( $this->hooked ) {
			return;
		}

		$this->addRenderHooks();
		$this->enqueueClassicApp();
	}

	private function addRenderHooks() {
		$this->hooked = true;

		$render = Fns::once( spreadArgs( [ $this, 'render' ] ) );

		Hooks::onAction( 'wpml_before_post_edit_translations_table' )
		     ->then( $render );

		Hooks::onAction( 'wpml_before_post_edit_translations_summary' )
		     ->then( $render );
	}

	public function enqueueClassicApp() {
		if ( \WPML_Block_Editor_Helper::is_block_editor_screen() ) {
			return;
		}

		Resources::enqueueApp( 'postEditTranslationEditor' )( self::localize() );
	}

	public static function localize() {
		return [
			'name' => 'wpml_translation_post_editor',
			'data' => [
				'endpoints' => [
					'setEditorMode'      => SetEditorMode::class,
					'getPtcEngineStatus' => GetPtcEngineStatus::class,
				],
				'urls' => [
					'tmdashboard' => admin_url( 'admin.php?page=tm%2Fmenu%2Fmain.php' ),
				],
				'nonces' => [
					'captureTranslationEditorData' => wp_create_nonce( 'wpml_posthog_capture_data_nonce' ),
				],
				'trackingMode' => PostHogState::getTrackingMode(),
			],
		];
	}

	public function render( $post ) {
		global $wp_post_types;

		if ( ! Translations::isOriginal( $post->ID, PostTranslations::get( $post->ID ) ) ) {
			return;
		}

		if ( ! apply_filters( 'wpml_tm_post_edit_tm_editor_selector_display', true, $post->ID ) ) {
			return;
		}

		list( $useTmEditor, $isWpmlEditorBlocked, $reason ) = \WPML_TM_Post_Edit_TM_Editor_Mode::get_editor_settings( $this->sitepress, $post->ID );

		list ( $enabledEditor, $editorModeFor ) = \WPML_TM_Post_Edit_TM_Editor_Mode::get_translation_editor_with_scope( null, $post->ID );
		if ( ! $useTmEditor || $isWpmlEditorBlocked ) {
			$enabledEditor = SetEditorMode::TRANSLATION_EDITOR_NATIVE;
		}

		/*
		 * wpmldev-8629 P2 M8 (review P3-2), the panel's rule (ruling Q-SE-6 (c))
		 * on this screen too: the WordPress Editor is never offered for a
		 * navigation menu - the editor resolver sends a menu to WPML's editors
		 * whatever the stored choice - so the box neither offers nor presses it.
		 * The stored fact stays visible to the app (`data-stored-native`): a
		 * click on a menu whose stored choice is the WordPress Editor is a
		 * switch AWAY from it (the dialog, `isSwitchingWpmlNative`), never a
		 * direct write at the stored scope.
		 */
		$nativeOffered = SiteEditorEntityTypes::offersNativeEditor( (string) $post->post_type );
		$storedNative  = SetEditorMode::TRANSLATION_EDITOR_NATIVE === $enabledEditor;
		if ( ! $nativeOffered && $storedNative ) {
			$enabledEditor = SetEditorMode::TRANSLATION_EDITOR_WPML;
		}

		$isSwitchKeepingTranslations = \WPML\TranslationPolicy\Policy\Policy::editorSwitchKeepsTranslations();

		$postTypeLabels = $wp_post_types[ $post->post_type ]->labels;
		$currentWpmlEditor     = (string) wpml_get_tm_sub_setting( 'doc_translation_method', ICL_TM_TMETHOD_ATE );

		$isAte = ICL_TM_TMETHOD_ATE === $currentWpmlEditor;

		$wpmlEditorName = $isAte
			? esc_attr__( 'Advanced Translation Editor', 'sitepress' )
			: esc_attr__( 'Classic Translation Editor', 'sitepress' );

		$sourceLang = $this->sitepress->get_element_language_details( $post->ID )->language_code;

		echo '
		<div 
			id="translation-editor-post-settings" 
			data-wpml-editor-name="' . $wpmlEditorName . '" 
			data-post-id="' . $post->ID . '" 
			data-post-type="' . $post->post_type . '" 
			data-enabled-editor="' . $enabledEditor . '" 
			data-wpml-editor-mode-for="' . $editorModeFor . '" 
			data-is-wpml-blocked="' . $isWpmlEditorBlocked . '" 
			data-wpml-blocked-reason="' . $reason . '" 
			data-native-offered="' . ( $nativeOffered ? '1' : '0' ) . '" 
			data-stored-native="' . ( $storedNative ? '1' : '0' ) . '" 
			data-switch-keeps-translations="' . ( $isSwitchKeepingTranslations ? '1' : '0' ) . '" 
			data-type-singular="' . $postTypeLabels->singular_name . '" 
			data-type-plural="' . $postTypeLabels->name . '"
			data-source-lang="'. $sourceLang .'"
		></div>
		';
		echo '<div id="icl-translation-dashboard"></div>';
	}

	private function registerPostHogAjaxCapture() {
		if ( PostHogState::isEnabled() ) {
			new SendDataToPostHog();
		}
	}
}
