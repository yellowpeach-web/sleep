<?php


namespace WPML\BlockEditor;

use WPML\BlockEditor\Blocks\LanguageSwitcher;
use WPML\LanguageSwitcher\SwitcherAssetsOptOut;
use WPML\LIB\WP\Hooks;
use WPML\Core\WP\App\Resources;
use function WPML\Container\make;
use function WPML\FP\spreadArgs;

class Loader implements \IWPML_Backend_Action, \IWPML_REST_Action {

	const BLOCK_LANGUAGE_SWITCHER            = 'wpml/language-switcher';
	const BLOCK_LANGUAGE_SWITCHER_NAVIGATION = 'wpml/navigation-language-switcher';

	const SCRIPT_NAME = 'wpml-blocks';

	private $localizedScriptData = [];

	private $cssLoaded = [];

	private $keyboard_script_enqueued = false;

	private $assetsOptOut;

	public function __construct( ?SwitcherAssetsOptOut $assetsOptOut = null ) {
		$this->assetsOptOut = $assetsOptOut ?: new SwitcherAssetsOptOut();
	}

	public function add_hooks() {
		if ( \WPML_Block_Editor_Helper::is_active() ) {
			$this->hookBlockRendering();
			$this->hookEditorAssets();

			return;
		}

		$this->hookBlockRendering();

		if ( did_action( 'after_setup_theme' ) ) {
			$this->hookEditorAssetsOnABlockTheme();

			return;
		}

		add_action( 'after_setup_theme', [ $this, 'hookEditorAssetsOnABlockTheme' ] );
	}

	public function hookEditorAssetsOnABlockTheme() {
		if ( $this->isBlockTheme() ) {
			$this->hookEditorAssets();
		}
	}

	private function hookBlockRendering() {
		Hooks::onAction( 'init' )
			->then( [ $this, 'registerBlocks' ] )
			->then( [ $this, 'maybeEnqueueNavigationBlockStyles' ] );

		add_filter(
			'render_block',
			[ $this, 'frontendPrintStyleIfBlockIsUsed' ],
			10,
			2
		);
	}

	private function hookEditorAssets() {
		Hooks::onAction( 'enqueue_block_assets' )
		     ->then( [ $this, 'enqueueBlockAssets' ] );

		Hooks::onAction( 'enqueue_block_editor_assets' )
		     ->then( [ $this, 'enqueueBlockEditorAssets' ] );

		Hooks::onFilter( 'block_categories_all', 10, 2 )
			->then( spreadArgs( [ $this, 'maybeRegisterCategory' ] ) );
	}

	public function shouldLoadEditorAssets() {
		return \WPML_Block_Editor_Helper::is_active() || \WPML_Block_Editor_Helper::is_site_editor_screen();
	}

	protected function isBlockTheme() {
		return function_exists( 'wp_is_block_theme' ) && wp_is_block_theme();
	}

	public function maybeRegisterCategory( $block_categories ) {
		return $this->shouldLoadEditorAssets()
			? $this->registerCategory( $block_categories )
			: $block_categories;
	}

	public function registerCategory( $block_categories ) {
		array_push(
			$block_categories,
			[
				'slug'  => 'wpml',
				/* translators: The name of the plugin, used as the title of its screens and of its menu. It is a product name and stays as it is. */
				'title' => __( 'WPML', 'sitepress' ),
				'icon'  => null,
			]
		);

		return $block_categories;
	}

	public function registerBlocks() {
		$LSLocalizedScriptData     = make( LanguageSwitcher::class )->register();
		$this->localizedScriptData = array_merge( $this->localizedScriptData, $LSLocalizedScriptData );
	}

	public function enqueueBlockAssets() {
		if ( ! $this->shouldEnqueueEditorAssets() ) {
			return;
		}

		$this->enqueueStyleIfBuilt( 'wpml-blocks-ui', '/dist/css/blocks/styles.css' );
	}

	public function enqueueBlockEditorAssets() {
		if ( ! $this->shouldEnqueueEditorAssets() ) {
			return;
		}

		$dependencies        = array_merge( [
			'wp-blocks',
			'wp-i18n',
			'wp-element',
		], $this->getEditorDependencies() );
		$localizedScriptData = [ 'name' => 'WPMLBlocks', 'data' => $this->localizedScriptData ];
		$this->enqueueBlocksScript( $localizedScriptData, $dependencies );
		$this->enqueueStyleIfBuilt( 'wpml-blocks-editor-ui', '/dist/css/blocks-editor/styles.css' );
	}

	private function shouldEnqueueEditorAssets() {
		return is_admin() && $this->shouldLoadEditorAssets();
	}

	private function enqueueBlocksScript( array $localizedScriptData, array $dependencies ) {
		$handle         = 'wpml-blocks-ui';
		$scriptRelative = '/dist/js/blocks/app.js';
		$scriptVersion  = $this->builtFileVersion( $scriptRelative ) ?: (string) ICL_SITEPRESS_SCRIPT_VERSION;

		wp_register_script(
			$handle,
			ICL_PLUGIN_URL . $scriptRelative,
			$dependencies,
			$scriptVersion,
			false
		);

		wp_localize_script( $handle, $localizedScriptData['name'], $localizedScriptData['data'] );
		wp_localize_script( $handle, 'wpmlOutboundLinks', \WPML\OutboundLinks\OutboundLinks::getJsConfig() );
		wp_enqueue_script( $handle );
		wp_set_script_translations( $handle, 'sitepress', WPML_PLUGIN_PATH . '/locale/jed' );
	}

	private function enqueueStyleIfBuilt( $handle, $relativePath ) {
		$version = $this->builtFileVersion( $relativePath );

		if ( null === $version ) {
			return;
		}

		wp_enqueue_style(
			$handle,
			ICL_PLUGIN_URL . $relativePath,
			[],
			$version
		);
	}

	protected function builtFileVersion( $relativePath ) {
		$path = WPML_PLUGIN_PATH . $relativePath;

		return file_exists( $path ) ? (string) filemtime( $path ) : null;
	}

	public function frontendPrintStyleIfBlockIsUsed( $content, $block ) {
		if ( is_admin() ) {
			return $content;
		}

		if (
			self::BLOCK_LANGUAGE_SWITCHER !== $block['blockName'] &&
			self::BLOCK_LANGUAGE_SWITCHER_NAVIGATION !== $block['blockName']
		) {
			return $content;
		}

		if ( '' === $content ) {
			return $content;
		}

		$cssOptedOut = $this->assetsOptOut->css();

		$css = $cssOptedOut ? '' : $this->styleLanguageSwitcher();
		if ( ! $this->assetsOptOut->js() ) {
			$this->enqueueLanguageSwitcherKeyboardScript();
		}

		if ( self::BLOCK_LANGUAGE_SWITCHER_NAVIGATION === $block['blockName'] ) {
			$css .= $cssOptedOut ? '' : $this->styleLanguageSwitcherNavigation();

			remove_filter(
				'render_block',
				[ $this, 'frontendPrintStyleIfBlockIsUsed' ]
			);
		}

		return ! empty( $css )
			? '<style>' . $css . '</style>' . $content
			: $content;
	}

	private function styleLanguageSwitcher() {
		if ( in_array( self::BLOCK_LANGUAGE_SWITCHER, $this->cssLoaded, true ) ) {
			return '';
		}

		$this->cssLoaded[] = self::BLOCK_LANGUAGE_SWITCHER;

		return $this->readStylesheet( '/dist/css/blocks/language-switcher.css' );
	}

	private function enqueueLanguageSwitcherKeyboardScript() {
		if ( $this->keyboard_script_enqueued ) {
			return;
		}

		$this->keyboard_script_enqueued = true;
		$scriptRelativePath             = '/dist/js/language-switcher-block-keyboard-navigation/app.js';
		$scriptPath                     = WPML_PLUGIN_PATH . $scriptRelativePath;
		$scriptVersion                  = file_exists( $scriptPath ) ? (string) filemtime( $scriptPath ) : (string) ICL_SITEPRESS_SCRIPT_VERSION;

		wp_register_script(
			'wpml-language-switcher-block-keyboard-navigation',
			ICL_PLUGIN_URL . $scriptRelativePath,
			[],
			$scriptVersion,
			true
		);

		wp_enqueue_script( 'wpml-language-switcher-block-keyboard-navigation' );
	}

	private function styleLanguageSwitcherNavigation() {
		if ( in_array( self::BLOCK_LANGUAGE_SWITCHER_NAVIGATION, $this->cssLoaded, true ) ) {
			return '';
		}

		$this->cssLoaded[] = self::BLOCK_LANGUAGE_SWITCHER_NAVIGATION;

		return $this->readStylesheet( '/dist/css/blocks/language-switcher-navigation.css' );
	}

	private function readStylesheet( $relativePath ) {
		$path = WPML_PLUGIN_PATH . $relativePath;

		if ( ! file_exists( $path ) ) {
			return '';
		}

		$css = file_get_contents( $path );

		return $css ?: '';
	}

	public function maybeEnqueueNavigationBlockStyles() {

		if ( ! wp_style_is( 'wp-block-navigation', 'enqueued' ) || ! wp_style_is( 'wp-block-navigation', 'queue' ) ) {
			add_filter( 'render_block', function ( $blockContent, $block ) {
				if ( $block['blockName'] === LanguageSwitcher::BLOCK_LANGUAGE_SWITCHER ) {
					wp_enqueue_style( 'wp-block-navigation' );
				}

				return $blockContent;
			}, 10, 2 );
		}
	}

	public function getEditorDependencies() {
		global $pagenow;

		if ( is_admin() && 'widgets.php' === $pagenow ) {
			return [ 'wp-edit-widgets' ];
		}

		return [ 'wp-editor' ];
	}
}
