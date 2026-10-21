<?php

use WPML\SuperGlobals\Server;

class WPML_Lang_Domain_Filters {

	private $wpml_url_converter;
	private $wpml_wp_api;
	private $debug_backtrace;

	private $hosts_by_language = null;

	public function __construct(
		WPML_URL_Converter $wpml_url_converter,
		WPML_WP_API $wpml_wp_api,
		WPML_Debug_BackTrace $debug_backtrace
	) {

		$this->wpml_url_converter = $wpml_url_converter;
		$this->wpml_wp_api        = $wpml_wp_api;
		$this->debug_backtrace    = $debug_backtrace;
	}

	public function add_hooks() {
		add_filter( 'upload_dir', array( $this, 'upload_dir_filter_callback' ) );
		add_filter( 'stylesheet_uri', array( $this, 'convert_url' ) );
		add_filter( 'option_siteurl', array( $this, 'siteurl_callback' ) );
		add_filter( 'content_url', array( $this, 'siteurl_callback' ) );
		add_filter( 'plugins_url', array( $this, 'siteurl_callback' ) );
		add_filter( 'login_url', array( $this, 'convert_auth_url' ) );
		add_filter( 'logout_url', array( $this, 'convert_logout_url' ) );
		add_filter( 'admin_url', array( $this, 'admin_url_filter' ), 10, 2 );
		add_filter( 'login_redirect', array( $this, 'convert_auth_url' ), 1, 1 );
	}

	public function remove_hooks() {
		remove_filter( 'upload_dir', array( $this, 'upload_dir_filter_callback' ) );
		remove_filter( 'stylesheet_uri', array( $this, 'convert_url' ) );
		remove_filter( 'option_siteurl', array( $this, 'siteurl_callback' ) );
		remove_filter( 'content_url', array( $this, 'siteurl_callback' ) );
		remove_filter( 'plugins_url', array( $this, 'siteurl_callback' ) );
		remove_filter( 'login_url', array( $this, 'convert_auth_url' ) );
		remove_filter( 'logout_url', array( $this, 'convert_logout_url' ) );
		remove_filter( 'admin_url', array( $this, 'admin_url_filter' ), 10 );
		remove_filter( 'login_redirect', array( $this, 'convert_auth_url' ), 1 );
	}

	public function convert_url( $url ) {
		return $this->wpml_url_converter->convert_url( $url );
	}

	public function convert_auth_url( $url ) {
		return $this->wpml_url_converter->convert_url( $url, $this->get_request_language() );
	}

	private function get_request_language() {
		$host = $this->get_request_host();
		if ( '' === $host ) {
			return null;
		}

		$language = array_search( $host, $this->get_hosts_by_language(), true );
		if ( false !== $language ) {
			return (string) $language;
		}

		return $host === $this->get_home_host() ? (string) wpml_get_setting( 'default_language' ) : null;
	}

	public function upload_dir_filter_callback( $upload_dir ) {
		$convertWithMatchingTrailingSlash = function ( $url ) {
			$hasTrailingSlash = '/' === substr( $url, -1 );
			$newUrl           = $this->wpml_url_converter->convert_url( $url );

			return $hasTrailingSlash ? trailingslashit( $newUrl ) : untrailingslashit( $newUrl );
		};

		$upload_dir['url']     = $convertWithMatchingTrailingSlash( $upload_dir['url'] );
		$upload_dir['baseurl'] = $convertWithMatchingTrailingSlash( $upload_dir['baseurl'] );

		return $upload_dir;
	}

	public function siteurl_callback( $url ) {
		$getting_network_site_url = $this->debug_backtrace->is_function_in_call_stack( 'get_admin_url' ) && is_multisite();

		if ( $this->debug_backtrace->is_function_in_call_stack( 'get_home_path', false ) || $getting_network_site_url ) {
			return $url;
		}

		$parsed_url = wpml_parse_url( $url );

		if ( ! is_array( $parsed_url ) || empty( $parsed_url['host'] ) ) {
			return $url;
		}

		$host = $this->get_request_host();

		if ( '' === $host || ! $this->is_own_host( $host ) ) {
			return $url;
		}

		return str_replace( $parsed_url['host'], $host, $url );
	}

	private function is_own_host( $host ) {
		return in_array( $host, $this->get_hosts_by_language(), true ) || $host === $this->get_home_host();
	}

	private function get_request_host() {
		return strtolower( (string) strtok( (string) Server::getServerName(), ':' ) );
	}

	private function get_hosts_by_language() {
		if ( null === $this->hosts_by_language ) {
			$this->hosts_by_language = WPML_Language_Domains::hostsOf( wpml_get_setting( 'language_domains' ) );
		}

		return $this->hosts_by_language;
	}

	private function get_home_host() {
		return WPML_Language_Domains::hostOf( (string) $this->wpml_url_converter->get_abs_home() );
	}

	public function admin_url_filter( $url, $path ) {
		if ( ( strpos( $url, 'http://' ) === 0
			   || strpos( $url, 'https://' ) === 0 )
			 && 'admin-ajax.php' === $path && $this->wpml_wp_api->is_front_end()
		) {
			$url = $this->convert_auth_url( $url );
		}

		return $url;
	}

	public function convert_logout_url( $logout_url ) {
		if ( $this->wpml_wp_api->is_front_end() ) {
			$logout_url = $this->convert_auth_url( $logout_url );
		}

		return $logout_url;
	}
}
