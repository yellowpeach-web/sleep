<?php

use WPML\BrowserLanguageRedirect\BarTexts;

class WPML_Browser_Redirect {

	const CHOSEN_LANGUAGE_COOKIE = '_icl_visitor_chosen_lang';

	const CHOSEN_LANGUAGE_DAYS_DEFAULT = 30;

	const CHOSEN_LANGUAGE_DAYS_MAX = 400;

	private $sitepress;

	private $bar_texts;

	public static function is_enabled( $settings ) {
		return is_array( $settings )
			&& (
				! empty( $settings['automatic_redirect'] )
				|| ! empty( $settings['suggest_browser_language'] )
				|| ! empty( $settings['redirect_to_chosen_language'] )
			);
	}

	public static function get_first_visit_mode( $automatic_redirect, $suggest_browser_language ) {
		if ( ! empty( $suggest_browser_language ) ) {
			return 'bar';
		}

		return in_array( (int) $automatic_redirect, [ 1, 2 ], true ) ? 'redirect' : 'keep';
	}

	public function __construct( $sitepress, $bar_texts = null ) {
		$this->sitepress = $sitepress;
		$this->bar_texts = $bar_texts;
	}

	public function init_hooks() {
		add_action( 'init', array( $this, 'init' ) );

		if ( $this->sitepress->get_setting( 'suggest_browser_language' ) ) {
			add_action( 'wpml_st_add_string_translation', [ BarTexts::class, 'on_string_translation_saved' ], 10, 4 );
		}
	}

	public function init() {
		if ( ! isset( $_GET['redirect_to'] ) &&
			! is_admin() && ! is_customize_preview() &&
			( ! isset( $_SERVER['REQUEST_URI'] ) || ! preg_match( '#wp-login\.php$#', preg_replace( '@\?(.*)$@', '', $_SERVER['REQUEST_URI'] ) ) )
		) {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		}
	}

	public function enqueue_scripts() {
		wp_register_script( 'wpml-browser-redirect', ICL_PLUGIN_URL . '/dist/js/browser-redirect/app.js', array(), ICL_SITEPRESS_SCRIPT_VERSION );

		$automatic_redirect = $this->sitepress->get_setting( 'automatic_redirect' );

		$all_languages = $this->get_ls_languages( false );
		$followable    = $this->get_ls_languages( true );

		$languages     = 1 === (int) $automatic_redirect ? $followable : $all_languages;
		$language_urls = [];
		$countries           = \WPML\LanguageEditor\ActiveLanguages::countryByCode();
		$language_identities = [];
		foreach ( $languages as $language ) {
			if ( isset( $language['default_locale'] ) && $language['default_locale'] ) {
				$default_locale                   = strtolower( $language['default_locale'] );
				$language_urls[ $default_locale ] = $language['url'];
				$language_parts                   = explode( '_', $default_locale );
				if ( count( $language_parts ) > 1 ) {
					foreach ( $language_parts as $language_part ) {
						if ( ! isset( $language_urls[ $language_part ] ) ) {
							$language_urls[ $language_part ] = $language['url'];
						}
					}
				}
			}
			$language_urls[ $language['language_code'] ] = $language['url'];

			$code                  = $language['language_code'];
			$language_identities[] = [
				'code'     => $code,
				'language' => \WPML\LanguageEditor\LanguageCodeResolution::publishedIdentity( $code )['language'],
				'country'  => isset( $countries[ $code ] ) ? $countries[ $code ] : null,
				'locale'   => isset( $language['default_locale'] ) ? strtolower( (string) $language['default_locale'] ) : '',
				'url'      => $language['url'],
			];
		}
		$http_host = \WPML\Cookie\CookieHost::get();
		$cookie    = array(
			'name'       => '_icl_visitor_lang_js',
			'domain'     => ( defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ? COOKIE_DOMAIN : $http_host ),
			'path'       => ( defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/' ),
			'expiration' => $this->sitepress->get_setting( 'remember_language' ),
		);

		$params = array(
			'pageLanguage' => defined( 'ICL_LANGUAGE_CODE' ) ? ICL_LANGUAGE_CODE : get_bloginfo( 'language' ),
			'languageUrls' => $language_urls,
			'languages'    => $language_identities,
			'cookie'       => $cookie,
		);

		if ( $this->is_configured_root_request() ) {
			$params['pageLanguage'] = '';
		}

		$active_codes             = $this->get_active_language_codes();
		$params['firstVisitMode'] = self::get_first_visit_mode( $automatic_redirect, $this->sitepress->get_setting( 'suggest_browser_language' ) );
		$params['allLanguages']   = $this->get_all_languages( $all_languages, $followable );
		$params['siteOrigins']    = $this->get_site_origins( $params['allLanguages'], $active_codes );
		$params['isSearch']       = (bool) is_search();
		$params['chosenLanguage'] = $this->get_chosen_language_params( $cookie, $active_codes );

		if ( 'bar' === $params['firstVisitMode'] ) {
			$params['bar'] = $this->get_bar_texts()->get();
		}

		$params = apply_filters( 'wpml_browser_redirect_language_params', $params );

		$enqueue = false;
		if ( $params && isset( $params['pageLanguage'], $params['languageUrls'] ) ) {
			wp_localize_script( 'wpml-browser-redirect', 'wpml_browser_redirect_params', $params );

			$enqueue = apply_filters( 'wpml_enqueue_browser_redirect_language', ! is_404() );
			if ( $enqueue ) {
				wp_enqueue_script( 'wpml-browser-redirect' );
			}
		}

		do_action( 'wpml_enqueued_browser_redirect_language', $enqueue, $params );
	}

	public static function sanitize_chosen_language_days( $days ) {
		$days = is_numeric( $days ) ? (int) $days : 0;

		if ( $days < 1 ) {
			return self::CHOSEN_LANGUAGE_DAYS_DEFAULT;
		}

		return min( $days, self::CHOSEN_LANGUAGE_DAYS_MAX );
	}

	private function get_bar_texts() {
		if ( ! $this->bar_texts ) {
			$this->bar_texts = new BarTexts( $this->sitepress );
		}

		return $this->bar_texts;
	}

	private function get_ls_languages( $skip_missing ) {
		$languages = $this->sitepress->get_ls_languages( [ 'skip_missing' => $skip_missing ] );

		return is_array( $languages ) ? $languages : [];
	}

	private function get_all_languages( array $all_languages, array $followable ) {
		$followable_codes = [];
		foreach ( $followable as $language ) {
			if ( isset( $language['language_code'] ) ) {
				$followable_codes[ (string) $language['language_code'] ] = true;
			}
		}

		$result = [];
		foreach ( $all_languages as $language ) {
			if ( ! isset( $language['language_code'], $language['url'] ) ) {
				continue;
			}
			$code = (string) $language['language_code'];

			$url = apply_filters( 'WPML_filter_link', $language['url'], $language );
			$url = apply_filters( 'wpml_ls_language_url', $url, $language );

			$result[] = [
				'code'    => $code,
				'url'     => (string) $url,
				'missing' => ! isset( $followable_codes[ $code ] ),
			];
		}

		return $result;
	}

	private function get_active_language_codes() {
		$active_languages = $this->sitepress->get_active_languages();

		return is_array( $active_languages ) ? array_map( 'strval', array_keys( $active_languages ) ) : [];
	}

	private function get_site_origins( array $all_languages, array $active_codes ) {
		$urls = [];

		foreach ( $active_codes as $code ) {
			$urls[] = $this->sitepress->language_url( $code );
		}

		foreach ( $all_languages as $language ) {
			$urls[] = $language['url'];
		}

		$origins = [];
		foreach ( $urls as $url ) {
			$origin = self::get_origin( $url );
			if ( null !== $origin && ! in_array( $origin, $origins, true ) ) {
				$origins[] = $origin;
			}
		}

		return $origins;
	}

	private static function get_origin( $url ) {
		if ( ! is_string( $url ) || '' === $url ) {
			return null;
		}

		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return null;
		}

		$scheme = strtolower( $parts['scheme'] );
		$origin = $scheme . '://' . strtolower( $parts['host'] );

		if ( isset( $parts['port'] ) ) {
			$port          = (int) $parts['port'];
			$default_ports = [
				'http'  => 80,
				'https' => 443,
			];
			if ( ! isset( $default_ports[ $scheme ] ) || $default_ports[ $scheme ] !== $port ) {
				$origin .= ':' . $port;
			}
		}

		return $origin;
	}

	private function get_chosen_language_params( array $cookie, array $active_codes ) {
		$enabled = (bool) apply_filters(
			'wpml_redirect_to_chosen_language',
			(bool) $this->sitepress->get_setting( 'redirect_to_chosen_language' )
		);

		$days = self::sanitize_chosen_language_days(
			$this->sitepress->get_setting( 'chosen_language_days', self::CHOSEN_LANGUAGE_DAYS_DEFAULT )
		);

		return [
			'enabled'   => $enabled,
			'languages' => $active_codes,
			'cookie'    => [
				'name'   => self::CHOSEN_LANGUAGE_COOKIE . ( is_multisite() ? '_' . get_current_blog_id() : '' ),
				'domain' => $cookie['domain'],
				'path'   => $cookie['path'],
				'maxAge' => $days * 86400,
			],
		];
	}

	protected function is_configured_root_request() {
		return WPML_Root_Page::is_configured_root_request();
	}
}
