<?php

namespace WPML\TranslationPolicy\Panel;

use WPML\TM\PostEditScreen\TranslationEditorPostSettings;

class ClassicBox {

	const SCRIPT_HANDLE = 'wpml-language-panel-classic';

	const DATA_GLOBAL = 'wpmlLanguagePanelClassic';

	const DEPENDENCIES = [ 'wp-element', 'wp-data', 'wp-components', 'wp-api-fetch', 'wp-i18n', 'wp-url' ];

	const SCREENS = [ 'post.php', 'post-new.php' ];

	private $postSettings;

	public function __construct( callable $postSettings ) {
		$this->postSettings = $postSettings;
	}

	public function enqueue() {
		global $pagenow;

		if ( ! defined( 'WPML_PUBLIC_DIR' ) ) {
			return;
		}

		$post = self::post( $pagenow );
		if ( ! $post instanceof \WP_Post || ! apply_filters( 'wpml_is_translated_post_type', false, $post->post_type ) ) {
			return;
		}

		if ( ! self::isClassicPostScreen( $pagenow ) ) {
			( $this->postSettings )()->addToPluginScreen();
		}

		$version = defined( 'WPML_VERSION' ) ? WPML_VERSION : ICL_SITEPRESS_SCRIPT_VERSION;

		wp_enqueue_style( self::SCRIPT_HANDLE, plugins_url( 'public/css/language-panel-classic.css', WPML_PUBLIC_DIR ), [ 'wp-components' ], $version );
		wp_enqueue_script( self::SCRIPT_HANDLE, plugins_url( 'public/js/language-panel-classic.js', WPML_PUBLIC_DIR ), self::DEPENDENCIES, $version, true );
		if ( defined( 'WPML_ROOT_DIR' ) ) {
			wp_set_script_translations( self::SCRIPT_HANDLE, 'wpml', WPML_ROOT_DIR . '/languages/' );
		}

		wp_add_inline_script(
			self::SCRIPT_HANDLE,
			'window.' . self::DATA_GLOBAL . ' = ' . wp_json_encode( self::data( $post ) ) . ';',
			'before'
		);
	}

	private static function post( $pagenow ) {
		if ( self::isClassicPostScreen( $pagenow ) ) {
			$post = get_post();

			return $post instanceof \WP_Post ? $post : null;
		}

		if ( ! apply_filters( 'wpml_enable_language_meta_box', false ) ) {
			return null;
		}

		$postId = (int) filter_input( INPUT_GET, 'post', FILTER_SANITIZE_NUMBER_INT );
		$post   = $postId > 0 ? get_post( $postId ) : null;

		return $post instanceof \WP_Post ? $post : null;
	}

	private static function isClassicPostScreen( $pagenow ): bool {
		return in_array( $pagenow, self::SCREENS, true )
			&& ! \WPML_Block_Editor_Helper::is_block_editor_screen();
	}

	private static function data( \WP_Post $post ): array {
		$type   = get_post_type_object( $post->post_type );
		$labels = $type ? $type->labels : null;

		return [
			'postId'         => (int) $post->ID,
			'postType'       => (string) $post->post_type,
			'postTypeLabels' => [
				'singular' => (string) ( $labels->singular_name ?? $post->post_type ),
				'plural'   => (string) ( $labels->name ?? $post->post_type ),
			],
		];
	}
}
