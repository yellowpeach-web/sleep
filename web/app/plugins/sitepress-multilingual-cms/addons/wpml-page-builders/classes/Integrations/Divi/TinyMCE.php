<?php

namespace WPML\Compatibility\Divi;

class TinyMCE implements \IWPML_Backend_Action {

	public function add_hooks() {
		if ( defined( 'WPML_TM_FOLDER' ) ) {
			add_filter( 'tiny_mce_before_init', [ $this, 'filterEditorAutoTags' ] );
		}
	}

	public function filterEditorAutoTags( $config ) {
		if ( class_exists( '\WPML_TM_Page' ) && \WPML_TM_Page::is_translation_editor_page() ) {
			$config['wpautop']      = false;
			$config['indent']       = true;
			$config['tadv_noautop'] = true;
		}

		return $config;
	}
}
