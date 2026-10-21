<?php

class WPML_Block_Editor_Translation_Connect implements IWPML_Backend_Action, IWPML_DIC_Action {

	public function add_hooks() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function enqueue() {
		if ( ! isset( $_GET['trid'], $_GET['lang'] ) ) {
			return;
		}
		$pagenow = isset( $GLOBALS['pagenow'] ) ? $GLOBALS['pagenow'] : '';
		if ( 'post-new.php' !== $pagenow && 'post.php' !== $pagenow ) {
			return;
		}

		$lang = \WPML\Language\RequestedLanguage::forPrivileged( sanitize_text_field( wp_unslash( $_GET['lang'] ) ) );
		if ( null === $lang ) {
			return;
		}

		$params = array(
			'wpml_editor' => 1,
			'trid'        => (int) $_GET['trid'],
			'lang'        => $lang,
		);

		$handle = 'wpml-block-editor-translation-connect';
		wp_enqueue_script(
			$handle,
			ICL_PLUGIN_URL . '/res/js/block-editor-translation-connect.js',
			array( 'wp-api-fetch', 'wp-url' ),
			ICL_SITEPRESS_SCRIPT_VERSION,
			true
		);
		wp_add_inline_script(
			$handle,
			'window.wpmlBlockEditorConnect = ' . wp_json_encode( $params ) . ';',
			'before'
		);
	}
}
