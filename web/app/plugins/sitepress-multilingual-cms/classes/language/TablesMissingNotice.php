<?php

namespace WPML\Language;

class TablesMissingNotice {

	public static function render() {
		if ( ! WpmlPresence::tablesMissingOnCurrentBlog() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! is_textdomain_loaded( 'sitepress' ) ) {
			load_plugin_textdomain( 'sitepress', false, basename( dirname( __DIR__, 2 ) ) . '/locale' );
		}

		echo '<div class="notice notice-error"><p>'
			. esc_html__( "WPML's database tables are missing on this site, so WPML is not running here. Restore the tables from a backup of this site, or deactivate WPML on this site.", 'sitepress' )
			. '</p></div>';
	}
}
