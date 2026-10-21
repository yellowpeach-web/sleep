<?php

namespace WPML\Language\Detection;

use WPML\Cookie\CookieHost;

class CookieLanguage {

	const CONTENT_EXTENSIONS = [ 'php', 'html', 'htm', 'xhtml', 'shtml', 'xml', 'rss', 'atom', 'json' ];

	private $cookie;

	private $defaultLanguage;

	public function __construct( \WPML_Cookie $cookie, $defaultLanguage ) {
		$this->cookie          = $cookie;
		$this->defaultLanguage = $defaultLanguage;
	}

	public function getAjaxCookieName( $isBackend ) {
		return $isBackend ? $this->getBackendCookieName() : $this->getFrontendCookieName();
	}

	public function getBackendCookieName() {
		return 'wp-wpml_current_admin_language_' . md5( (string) $this->get_cookie_domain() );
	}

	public function getFrontendCookieName() {
		return 'wp-wpml_current_language';
	}

	public function get( $cookieName ) {
		global $wpml_language_resolution;

		$lang = $this->getRaw( $cookieName );

		return $wpml_language_resolution->is_language_active( $lang ) ? $lang : $this->defaultLanguage;
	}

	public function getRaw( $cookieName ) {
		$cookie_value = esc_attr( $this->cookie->get_cookie( $cookieName ) );

		return $cookie_value ? substr( $cookie_value, 0, 10 ) : null;
	}


	public function set( $cookieName, $lang_code ) {
		global $sitepress;

		if ( is_user_logged_in() ) {
			if ( ! $this->cookie->headers_sent() ) {
				$is_ajax_request = isset( $_POST['_ajax_nonce'] ) || defined( 'DOING_AJAX' );
				if ( ! $this->is_content_request() || $is_ajax_request ) {
					return;
				}

				$current_cookie_value = $this->cookie->get_cookie( $cookieName );
				if ( ! $current_cookie_value || $current_cookie_value !== $lang_code ) {
					$cookie_domain = $this->get_cookie_domain();
					$cookie_path   = defined( 'COOKIEPATH' ) ? COOKIEPATH : '/';
					$this->cookie->set_cookie(
						$cookieName,
						$lang_code,
						time() + DAY_IN_SECONDS,
						$cookie_path,
						$cookie_domain
					);
				}
			}
		} elseif ( $sitepress->get_setting( \WPML_Cookie_Setting::COOKIE_SETTING_FIELD ) ) {
			$wpml_cookie_scripts = new \WPML_Cookie_Scripts( $cookieName, $sitepress->get_current_language() );
			$wpml_cookie_scripts->add_hooks();
		}

		$_COOKIE[ $cookieName ] = $lang_code;

		do_action( 'wpml_language_cookie_added', $lang_code );
	}

	private function is_content_request() {
		$request_uri = isset( $_SERVER['REQUEST_URI'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) )
			: '';
		$path        = basename( (string) preg_replace( '@[?#].*$@', '', $request_uri ) );

		if ( ! preg_match( '@\.([A-Za-z][A-Za-z0-9]{0,4})$@', $path, $extension ) ) {
			return true;
		}

		return in_array( strtolower( $extension[1] ), self::CONTENT_EXTENSIONS, true );
	}

	public function get_cookie_domain() {

		return defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : CookieHost::get();
	}
}
