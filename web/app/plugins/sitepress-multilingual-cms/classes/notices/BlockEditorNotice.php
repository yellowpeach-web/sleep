<?php

namespace WPML\Notices;

class BlockEditorNotice {

	const HANDLE = 'wpml-block-editor-notices';

	public static function enqueue( array $notice ) {
		wp_enqueue_script(
			self::HANDLE,
			ICL_PLUGIN_URL . '/res/js/block-editor-notices.js',
			[ 'wp-data', 'wp-notices', 'wp-dom-ready' ],
			ICL_SITEPRESS_SCRIPT_VERSION,
			true
		);

		wp_add_inline_script(
			self::HANDLE,
			'window.wpmlBlockEditorNotices = ( window.wpmlBlockEditorNotices || [] ).concat( [ ' . wp_json_encode( $notice ) . ' ] );',
			'before'
		);
	}
}
