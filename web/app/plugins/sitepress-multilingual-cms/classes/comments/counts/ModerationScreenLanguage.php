<?php

namespace WPML\Comments\Counts;

use WPML\Language\Detection\Ajax;

class ModerationScreenLanguage {

	const HANDLE = 'admin-comments';

	private $sitepress;

	public function __construct( ?\SitePress $sitepress = null ) {
		$this->sitepress = $sitepress;
	}

	public function addScript() {
		$language = $this->currentLanguage();
		if ( ! is_string( $language ) || '' === $language || 'all' === $language ) {
			return;
		}

		wp_add_inline_script( self::HANDLE, $this->script( $language ), 'before' );
	}

	public function script( $language ) {
		return sprintf(
			'(function ($, param, lang) {
	if (!$ || !$.ajaxPrefilter) {
		return;
	}
	$.ajaxPrefilter(function (options) {
		var data = options.data;
		if ("string" !== typeof data || -1 === String(options.url).indexOf("admin-ajax.php")
			|| !/(^|&)action=(dim-comment|delete-comment|replyto-comment|fetch-list)(&|$)/.test(data)
			|| new RegExp("(^|&)" + param + "=").test(data)) {
			return;
		}
		options.data = data + "&" + param + "=" + encodeURIComponent(lang);
	});
}(window.jQuery, %s, %s));',
			wp_json_encode( Ajax::COMMENT_SCREEN_LANG_PARAM ),
			wp_json_encode( $language )
		);
	}

	private function currentLanguage() {
		if ( ! $this->sitepress ) {
			global $sitepress;

			$this->sitepress = $sitepress instanceof \SitePress ? $sitepress : null;
		}

		return $this->sitepress ? $this->sitepress->get_current_language() : null;
	}
}
