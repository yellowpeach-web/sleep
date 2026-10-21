<?php

class WPML_ST_Outdated_Stand_In {

	public function get_current_string_language( $name = null ) {
		global $sitepress;

		return $sitepress instanceof SitePress ? (string) $sitepress->get_current_language() : null;
	}

	public function get_admin_string_filter( $language = null ) {
		return null;
	}

	public function __call( $name, $arguments ) {
		return null;
	}
}
