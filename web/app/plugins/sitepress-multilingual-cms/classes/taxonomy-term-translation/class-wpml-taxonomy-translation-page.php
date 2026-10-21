<?php

class WPML_Taxonomy_Translation_Page {

	public static function is_current() {
		return ( new WPML_WP_API() )->is_taxonomy_translation_page();
	}

}
