<?php

namespace WPML\Setup;

class LanguageEditorWizard {

	public static function enqueue() {
		if ( ! class_exists( \WPML\LanguageEditor\PageData::class ) ) {
			return;
		}

		$base = defined( 'WPML_PUBLIC_DIR' ) ? WPML_PUBLIC_DIR : __FILE__;

		wp_enqueue_script(
			'wpml-node-modules',
			plugins_url( 'public/js/node-modules.js', $base ),
			array(),
			self::assetVersion( 'js/node-modules.js' ),
			true
		);
		wp_enqueue_script(
			'wpml-wc-language-editor',
			plugins_url( 'public/js/wc-language-editor.js', $base ),
			array( 'wpml-node-modules', 'wp-i18n' ),
			self::assetVersion( 'js/wc-language-editor.js' ),
			true
		);
		wp_set_script_translations(
			'wpml-wc-language-editor',
			'wpml',
			defined( 'WPML_ROOT_DIR' ) ? WPML_ROOT_DIR . '/languages/' : ''
		);
		wp_enqueue_style(
			'wpml-language-editor-tailwind',
			plugins_url( 'public/css/tailwind.css', $base ),
			array(),
			self::assetVersion( 'css/tailwind.css' )
		);
		wp_enqueue_style(
			'wpml-wc-language-editor',
			plugins_url( 'public/css/wc-language-editor.css', $base ),
			array( 'wpml-language-editor-tailwind' ),
			self::assetVersion( 'css/wc-language-editor.css' )
		);

		self::localizeBootstrap();
	}

	private static function assetVersion( $relPath ) {
		if ( defined( 'WPML_PUBLIC_DIR' ) ) {
			$file = WPML_PUBLIC_DIR . '/' . $relPath;
			if ( is_readable( $file ) ) {
				$mtime = filemtime( $file );
				if ( $mtime ) {
					return (string) $mtime;
				}
			}
		}

		return ICL_SITEPRESS_VERSION;
	}

	private static function localizeBootstrap() {
		$endpoints = array();
		foreach ( \WPML\LanguageEditor\Endpoints::get() as $action => $class ) {
			$endpoints[ $action ] = array(
				'endpoint' => $class,
				'nonce'    => \WPML\LIB\WP\Nonce::create( $class ),
			);
		}

		$data = array_merge(
			array(
				'endpoints' => $endpoints,
				'surface'        => 'wizard',
				'showCodeFields' => true,
			),
			\WPML\LanguageEditor\PageData::bootstrap()
		);

		wp_add_inline_script(
			'wpml-wc-language-editor',
			'window.wpmlLanguageEditor = ' . wp_json_encode( $data ) . ';',
			'before'
		);
	}

}
