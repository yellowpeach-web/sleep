<?php

use WPML\SuperGlobals\Request;

class WPML_Canonicals_Hooks {

	private $sitepress;

	private $url_converter;

	private $is_current_request_root_callback;

	public function __construct( SitePress $sitepress, WPML_URL_Converter $url_converter, $is_current_request_root_callback ) {
		$this->sitepress                        = $sitepress;
		$this->url_converter                    = $url_converter;
		$this->is_current_request_root_callback = $is_current_request_root_callback;
	}

	public function add_hooks() {
		$urls             = $this->sitepress->get_setting( 'urls' );
		$lang_negotiation = (int) $this->sitepress->get_setting( 'language_negotiation_type' );

		if ( WPML_LANGUAGE_NEGOTIATION_TYPE_DIRECTORY === $lang_negotiation
			 && ! empty( $urls['directory_for_default_language'] )
		) {
			add_action( 'template_redirect', array( $this, 'redirect_pages_from_root_to_default_lang_dir' ) );
			add_action( 'template_redirect', [ $this, 'redirectArchivePageToDefaultLangDir' ] );
		} elseif ( WPML_LANGUAGE_NEGOTIATION_TYPE_PARAMETER === $lang_negotiation ) {
			add_filter( 'redirect_canonical', array( $this, 'prevent_redirection_with_translated_paged_content' ) );
		}

		if ( WPML_LANGUAGE_NEGOTIATION_TYPE_DIRECTORY === $lang_negotiation ) {
			add_filter( 'redirect_canonical', [ $this, 'prevent_redirection_of_frontpage_on_secondary_language' ], 10, 2 );
		}

		if ( WPML_LANGUAGE_NEGOTIATION_TYPE_DOMAIN === $lang_negotiation ) {
			add_filter( 'redirect_canonical', [ $this, 'redirect_post_id_to_its_language_domain' ], 9 );
		}

		add_filter( 'pre_redirect_guess_404_permalink', [ $this, 'guess_404_permalink_in_post_language' ] );

		add_filter( 'redirect_canonical', [ $this, 'refuse_redirect_to_an_undefined_language' ] );
	}

	public function redirect_post_id_to_its_language_domain( $redirect_url ) {
		if ( ! is_404() || ! is_string( $redirect_url ) || '' === $redirect_url ) {
			return $redirect_url;
		}

		list( $redirect_origin, $redirect_path ) = $this->split_url_origin_and_path( $redirect_url );
		if ( '' === $redirect_origin ) {
			return $redirect_url;
		}

		$post_id = max( (int) get_query_var( 'p' ), (int) get_query_var( 'page_id' ), (int) get_query_var( 'attachment_id' ) );
		$post    = $post_id > 0 ? get_post( $post_id ) : null;
		if ( ! $post instanceof WP_Post ) {
			return $redirect_url;
		}

		$language = (string) $this->sitepress->get_language_for_element( $post->ID, 'post_' . $post->post_type );
		if ( '' === $language
			 || $language === $this->sitepress->get_current_language()
			 || ! array_key_exists( $language, $this->sitepress->get_active_languages() )
		) {
			return $redirect_url;
		}

		$permalink = get_permalink( $post );
		list( $origin, $path ) = is_string( $permalink ) ? $this->split_url_origin_and_path( $permalink ) : array( '', '' );
		if ( '' === $origin || 0 !== strpos( $redirect_path, $path ) ) {
			return $redirect_url;
		}

		return $origin . substr( $redirect_url, strlen( $redirect_origin ) );
	}

	private function split_url_origin_and_path( $url ) {
		if ( ! preg_match( '#^([a-z][a-z0-9+.\-]*://[^/?\#]+)([^?\#]*)#i', $url, $matches ) ) {
			return array( '', '' );
		}

		return array( $matches[1], '' === $matches[2] ? '/' : $matches[2] );
	}

	public function guess_404_permalink_in_post_language( $pre ) {
		if ( null !== $pre || $this->request_url_states_a_language() ) {
			return $pre;
		}

		remove_filter( 'pre_redirect_guess_404_permalink', [ $this, 'guess_404_permalink_in_post_language' ] );
		add_filter( 'wpml_use_permalink_of_post_translation', [ $this, 'do_not_use_permalink_of_post_translation' ] );
		try {
			return redirect_guess_404_permalink();
		} finally {
			remove_filter( 'wpml_use_permalink_of_post_translation', [ $this, 'do_not_use_permalink_of_post_translation' ] );
			add_filter( 'pre_redirect_guess_404_permalink', [ $this, 'guess_404_permalink_in_post_language' ] );
		}
	}

	private function request_url_states_a_language() {
		if ( $this->sitepress->get_current_language() !== $this->sitepress->get_default_language() ) {
			return true;
		}

		$negotiation = (int) $this->sitepress->get_setting( 'language_negotiation_type' );

		if ( WPML_LANGUAGE_NEGOTIATION_TYPE_PARAMETER === $negotiation ) {
			return '' !== Request::param( 'lang' );
		}

		if ( WPML_LANGUAGE_NEGOTIATION_TYPE_DIRECTORY === $negotiation ) {
			return $this->request_path_opens_with_default_language_dir();
		}

		return false;
	}

	private function request_path_opens_with_default_language_dir() {
		$urls = $this->sitepress->get_setting( 'urls' );

		if ( empty( $urls['directory_for_default_language'] ) ) {
			return false;
		}

		$lang = $this->getUrlLanguageCode( $this->sitepress->get_default_language() );
		$path = $this->get_request_path_below_home();

		return $path === '/' . $lang || 0 === strpos( $path, '/' . $lang . '/' );
	}

	private function get_request_path_below_home() {
		$request_uri    = isset( $_SERVER['REQUEST_URI'] )
			? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) )
			: '';
		$request_path   = (string) wpml_parse_url( $request_uri, PHP_URL_PATH );
		$install_subdir = wpml_parse_url( (string) $this->url_converter->get_abs_home(), PHP_URL_PATH );

		$path = is_string( $install_subdir ) && '' !== $install_subdir
			? preg_replace( '#^' . preg_quote( $install_subdir, '#' ) . '#', '', $request_path )
			: $request_path;

		return '/' . ltrim( (string) $path, '/' );
	}

	public function do_not_use_permalink_of_post_translation() {
		return false;
	}

	public function redirect_pages_from_root_to_default_lang_dir() {
		global $wp_query;

		if ( ! ( ( $wp_query->is_page() || $wp_query->is_posts_page ) && ! call_user_func( $this->is_current_request_root_callback ) ) ) {
			return;
		}

		$lang           = $this->getUrlLanguageCode( $this->sitepress->get_current_language() );
		$current_uri    = $_SERVER['REQUEST_URI'];
		$abs_home       = $this->url_converter->get_abs_home();
		$install_subdir = wpml_parse_url( $abs_home, PHP_URL_PATH );

		$actual_uri = is_string( $install_subdir )
			? preg_replace( '#^' . preg_quote( $install_subdir, '#' ) . '#', '', $current_uri )
			: $current_uri;
		$actual_uri = '/' . ltrim( $actual_uri, '/' );

		if ( 0 === strpos( $actual_uri, '/' . $lang ) ) {
			return;
		}

		$canonical_uri = is_string( $install_subdir )
			? trailingslashit( $install_subdir ) . $lang . $actual_uri
			: '/' . $lang . $actual_uri;
		$canonical_uri = user_trailingslashit( $canonical_uri );
		$this->redirectTo( $canonical_uri );
	}

	public function redirectArchivePageToDefaultLangDir() {
		$isValidForRedirect = is_archive() && ! call_user_func( $this->is_current_request_root_callback );
		if ( ! $isValidForRedirect ) {
			return;
		}

		$currentUri = $_SERVER['REQUEST_URI'];
		$lang       = $this->sitepress->get_current_language();

		$lang = $this->getUrlLanguageCode( $lang );

		$home_url        = rtrim( $this->url_converter->get_abs_home(), '/' );
		$parsed_site_url = wp_parse_url( $home_url );

		if ( isset( $parsed_site_url['path'] ) ) {
			$path = $parsed_site_url['path'];

			if ( ! empty( $path ) && strpos( $currentUri, $path ) === 0 ) {
				$currentUri = substr( $currentUri, strlen( $path ) );
			}
		}

		if ( 0 !== strpos( $currentUri, '/' . $lang ) ) {
			$canonicalUri = user_trailingslashit(
				$home_url . '/' . $lang . $currentUri
			);

			$this->redirectTo( $canonicalUri );
		}
	}

	private function redirectTo( $uri ) {
		$this->sitepress->get_wp_api()->wp_safe_redirect( $uri, 301 );
	}

	private function getUrlLanguageCode( $lang ) {
		$map = apply_filters( 'wpml_language_codes_map', array( $lang => $lang ) );

		return isset( $map[ $lang ] ) && is_string( $map[ $lang ] ) && '' !== $map[ $lang ]
			? $map[ $lang ]
			: $lang;
	}

	public function prevent_redirection_of_frontpage_on_secondary_language( $redirect_url, $requested_url ) {
		if ( ! is_front_page() ) {
			return $redirect_url;
		}

		if ( substr( get_option( 'permalink_structure' ), - 1 ) !== '/' ) {
			if ( $redirect_url === $requested_url . '/' ) {
				return false;
			}

			$redirect_url = untrailingslashit( $redirect_url );
		}

		return $redirect_url;
	}

	public function prevent_redirection_with_translated_paged_content( $redirect_url ) {
		if ( ! is_singular() || ! isset( $_GET['lang'] ) ) {
			return $redirect_url;
		}

		$page = (int) get_query_var( 'page' );

		if ( $page < 2 ) {
			return $redirect_url;
		}

		return false;
	}

	public function refuse_redirect_to_an_undefined_language( $redirect_url ) {
		if ( ! $redirect_url ) {
			return $redirect_url;
		}

		$queried_object = $this->post_this_redirect_is_about();
		if ( ! $queried_object ) {
			return $redirect_url;
		}

		$language_code = $this->sitepress->get_language_for_element(
			$queried_object->ID,
			'post_' . $queried_object->post_type
		);

		if ( '' === (string) $language_code
			 || in_array( $language_code, $this->sitepress->get_supported_language_codes(), true )
		) {
			return $redirect_url;
		}

		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();

		return false;
	}

	private function post_this_redirect_is_about() {
		$queried_object = get_queried_object();
		if ( $queried_object instanceof WP_Post ) {
			return $queried_object;
		}

		if ( ! is_404() ) {
			return null;
		}

		$name = get_query_var( 'name' );
		if ( '' === (string) $name ) {
			$name = get_query_var( 'pagename' );
		}
		if ( '' === (string) $name ) {
			return null;
		}

		$post_type  = (string) get_query_var( 'post_type' );
		$post_types = '' !== $post_type ? array( $post_type ) : get_post_types( array( 'public' => true ) );

		$post = get_page_by_path( $name, OBJECT, $post_types );
		if ( ! ( $post instanceof WP_Post ) ) {
			return null;
		}

		$status = get_post_status_object( $post->post_status );

		return $status && ( $status->public || $status->private ) ? $post : null;
	}
}
