<?php

namespace WPML\Upgrade\Commands;

class DisableAutoloadForUnusedOptions implements \IWPML_Upgrade_Command {

	const OPTIONS = [
		'otgs-installer-log',
		'otgs_active_components',
		'wpml_shortcode_list',
		'wpml_strings_need_links_fixed',
		'icl_st_settings',
	];

	const PB_SHORTCODE_FLAG = 'wpml_pb_has_shortcode_settings';

	private $results;

	public function run() {
		$start_version  = get_option( \WPML_Installation::WPML_START_VERSION_KEY );
		$is_new_install = ICL_SITEPRESS_VERSION === $start_version;
		if ( $is_new_install ) {
			$this->results = true;
			return $this->results;
		}

		$this->disable_autoload( self::OPTIONS );
		$this->seed_pb_shortcode_flag();

		$this->results = true;
		return $this->results;
	}

	private function disable_autoload( array $options ) {
		if ( function_exists( 'wp_set_options_autoload' ) ) {
			wp_set_options_autoload( $options, false );
			return;
		}

		global $wpdb;
		$in = wpml_prepare_in( $options );
		$wpdb->query(
			"UPDATE {$wpdb->options} SET autoload = 'off'
			 WHERE option_name IN ($in) AND autoload IN ('yes','on','auto','auto-on')"
		);
	}

	private function seed_pb_shortcode_flag() {
		$st = get_option( 'icl_st_settings' );
		update_option(
			self::PB_SHORTCODE_FLAG,
			( is_array( $st ) && ! empty( $st['pb_shortcode'] ) ) ? 1 : 0,
			true
		);
	}

	public function run_admin() {
		return $this->run();
	}

	public function run_ajax() {
		return null;
	}

	public function run_frontend() {
		return null;
	}

	public function get_results() {
		return $this->results;
	}
}
