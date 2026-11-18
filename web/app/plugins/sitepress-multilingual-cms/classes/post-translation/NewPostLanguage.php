<?php

namespace WPML\Posts;

class NewPostLanguage {

	private $sitepress;

	public function __construct( \SitePress $sitepress ) {
		$this->sitepress = $sitepress;
	}

	public function get( $requestLanguage, $postId, $storedLanguage ) {
		if ( ! $requestLanguage || $storedLanguage ) {
			return $requestLanguage;
		}

		$language = apply_filters( 'wpml_new_post_language', $requestLanguage, (int) $postId );

		return is_string( $language ) && $this->sitepress->is_active_language( $language ) ? $language : $requestLanguage;
	}
}
