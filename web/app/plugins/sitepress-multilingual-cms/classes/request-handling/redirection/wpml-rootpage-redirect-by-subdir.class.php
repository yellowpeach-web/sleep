<?php

class WPML_Rootpage_Redirect_By_Subdir extends WPML_Redirect_By_Subdir {

	private $urls;

	public function __construct( $urls, &$request_handler, &$url_converter, &$lang_resolution ) {
		parent::__construct( $url_converter, $request_handler, $lang_resolution );
		$this->urls = $urls;
	}

	public function get_redirect_target() {
		global $wpml_url_filters;

		$current_url = $this->get_current_request_url();

		$target = parent::get_redirect_target();

		if ( ! $target ) {
			$target = $wpml_url_filters->filter_root_permalink( $current_url );
		}

		if ( $target && $this->is_same_url( $target, $current_url ) ) {
			$target = false;
		}

		if ( $target === false ) {
			$this->maybe_setup_rootpage();
		}

		return $target;
	}

	private function get_current_request_url() {
		$abs_home       = untrailingslashit( $this->url_converter->get_abs_home() );
		$install_subdir = wpml_parse_url( $abs_home, PHP_URL_PATH );
		$request_uri    = (string) $this->request_handler->get_request_uri();

		if ( is_string( $install_subdir ) && '' !== trim( $install_subdir, '/' ) ) {
			$request_uri = (string) preg_replace(
				'#^' . preg_quote( $install_subdir, '#' ) . '#',
				'',
				$request_uri
			);
		}

		return $abs_home . '/' . ltrim( $request_uri, '/' );
	}

	private function is_same_url( $url, $other_url ) {
		return untrailingslashit( (string) $url ) === untrailingslashit( (string) $other_url );
	}

	private function maybe_setup_rootpage() {
		if ( ! WPML_Root_Page::is_current_request_root() ) {
			return;
		}

		if ( WPML_Root_Page::uses_html_root() ) {
			$result = \WPML\UrlHandling\RootPage\HtmlFile::resolve( $this->urls['root_html_file_path'] );

			if ( $result['valid'] ) {
				include $result['resolved_path'];
				exit;
			}

			self::log_invalid_root_file_once( $this->urls['root_html_file_path'], $result['error_code'] );

			$root_page_actions = wpml_get_root_page_actions_obj();
			$root_page_actions->wpml_home_url_setup_root_page();
			return;
		}

		$root_page_actions = wpml_get_root_page_actions_obj();
		$root_page_actions->wpml_home_url_setup_root_page();
	}

	private static function log_invalid_root_file_once( $raw_path, $error_code ) {
		self::flag_invalid_root_file( $raw_path, $error_code );

		$key = 'wpml_root_html_file_invalid_' . md5( $raw_path . '|' . $error_code );
		if ( get_transient( $key ) ) {
			return;
		}
		set_transient( $key, 1, DAY_IN_SECONDS );

		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( sprintf(
				'[WPML] Root HTML file is invalid (code: %s, value: %s). See WPML > Settings > URLs and SEO.',
				$error_code,
				$raw_path
			) );
		}
	}

	private static function flag_invalid_root_file( $raw_path, $error_code ) {
		$stored = get_option( 'wpml_root_html_file_invalid', false );

		if ( is_array( $stored )
			&& isset( $stored['raw_path'], $stored['error_code'] )
			&& $stored['raw_path'] === $raw_path
			&& $stored['error_code'] === $error_code
		) {
			return;
		}

		update_option( 'wpml_root_html_file_invalid', [
			'raw_path'   => $raw_path,
			'error_code' => $error_code,
			'noticed_at' => time(),
		], false );
	}
}
