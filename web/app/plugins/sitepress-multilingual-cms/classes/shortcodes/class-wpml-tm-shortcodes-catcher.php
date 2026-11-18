<?php

class WPML_TM_Shortcodes_Catcher implements IWPML_Action {

	public function add_hooks() {
		add_filter( 'pre_do_shortcode_tag', array( $this, 'register_shortcode' ), 10, 2 );
	}

	private $known = array();

	public function register_shortcode( $return, $tag ) {
		$blog_id = get_current_blog_id();
		if ( $tag && ( ! isset( $this->known[ $blog_id ] ) || ! in_array( $tag, $this->known[ $blog_id ], true ) ) ) {
			$registered_shortcodes = get_option( WPML_TM_XLIFF_Shortcodes::SHORTCODE_STORE_OPTION_KEY, array() );
			if ( ! in_array( $tag, $registered_shortcodes, true ) ) {
				$registered_shortcodes[] = $tag;
				update_option( WPML_TM_XLIFF_Shortcodes::SHORTCODE_STORE_OPTION_KEY, $registered_shortcodes, false );
			}
			$this->known[ $blog_id ] = is_array( $registered_shortcodes ) ? $registered_shortcodes : array();
		}

		return $return;
	}
}
