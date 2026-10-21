<?php


use WPML\Language\Detection\CookieLanguage;
use WPML\UrlHandling\WPLoginUrlConverterRules;
use WPML\FP\Obj;

abstract class WPML_Request {

	protected $url_converter;
	protected $active_languages;
	protected $default_language;

	protected $cookieLanguage;

	private $language_from_referer = false;

	public function __construct(
		WPML_URL_Converter $url_converter,
		$active_languages,
		$default_language,
		CookieLanguage $cookieLanguage
	) {
		$this->url_converter    = $url_converter;
		$this->active_languages = $active_languages;
		$this->default_language = $default_language;
		$this->cookieLanguage   = $cookieLanguage;
	}

	abstract protected function get_cookie_name();

	abstract public function get_requested_lang();

	public function get_request_uri( $filter = null ) {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '/';
		if ( $filter !== null ) {
			$request_uri = filter_var( $request_uri, $filter );
		}

		return $request_uri;
	}

	public function get_request_uri_lang() {
		if ( apply_filters( 'wpml_should_skip_saving_language_in_cookies', false ) ) {
			return false;
		}

		$req_url = isset( $_SERVER['HTTP_HOST'] )
			? untrailingslashit( $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] ) : '';

		return $this->url_converter->get_language_from_url( $req_url );
	}

	public function get_cookie_lang() {
		return $this->cookieLanguage->get( $this->get_cookie_name() );
	}

	public function get_raw_cookie_lang() {
		return $this->cookieLanguage->getRaw( $this->get_cookie_name() );
	}

	public function is_language_from_referer() {
		return $this->language_from_referer;
	}

	protected function get_referer_lang() {
		$this->language_from_referer = false;

		$query = $this->get_admin_referer_query();
		if ( null === $query['path'] ) {
			return null;
		}

		$args  = $query['args'];
		$rules = [
			function () use ( $args ) {
				return isset( $args['lang'] ) && is_string( $args['lang'] ) && 'all' !== $args['lang']
					? \WPML\Language\ActiveLanguagesReadModel::canonical( $args['lang'] )
					: null;
			},
			function () use ( $args, $query ) {
				return 'post.php' === $query['path'] && isset( $args['action'], $args['post'] ) && 'edit' === $args['action']
					? $this->get_post_lang( $args['post'] )
					: null;
			},
			function () use ( $args ) {
				return isset( $args['p'] ) ? $this->get_post_lang( $args['p'] ) : null;
			},
		];

		$language = null;
		foreach ( $rules as $rule ) {
			$candidate = $rule();
			if ( is_string( $candidate ) && in_array( $candidate, $this->active_languages, true ) ) {
				$language = $candidate;
				break;
			}
		}

		if ( null === $language ) {
			return null;
		}

		$this->language_from_referer = true;

		return $language;
	}

	protected function get_frontend_referer_lang() {
		$this->language_from_referer = false;

		$referer = $this->lowercase_origin( $this->get_absolute_raw_referer() );
		if ( ! $this->is_frontend_referer( $referer ) || $this->is_bare_home( $referer ) ) {
			return null;
		}

		$language = $this->url_converter->get_language_from_url( $referer );
		if ( ! is_string( $language ) || ! in_array( $language, $this->active_languages, true ) ) {
			return null;
		}

		$this->language_from_referer = true;

		return $language;
	}

	private function get_absolute_raw_referer() {
		$referer = (string) wp_get_raw_referer();

		if ( ! preg_match( '#^https?://#i', $referer ) && isset( $_SERVER['HTTP_REFERER'] ) ) {
			$referer = esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
		}

		return $referer;
	}

	private function is_bare_home( $referer ) {
		$home = trailingslashit( $this->strip_scheme( strtolower( (string) get_option( 'home' ) ) ) );

		return trailingslashit( $this->strip_scheme( $referer ) ) === $home;
	}

	private function is_frontend_referer( $referer ) {
		if ( ! preg_match( '#^https?://#', $referer ) ) {
			return false;
		}

		$adminUrl = $this->strip_scheme( $this->lowercase_origin( (string) admin_url() ) );
		if ( '' !== $adminUrl && 0 === strpos( $this->strip_scheme( $referer ), $adminUrl ) ) {
			return false;
		}

		$page = trailingslashit( $this->strip_scheme( (string) preg_replace( '#[?\#].*$#', '', $referer ) ) );
		if ( $this->is_login_page_path( $page ) ) {
			return false;
		}
		foreach ( $this->get_site_roots() as $root ) {
			if ( 0 === strpos( $page, $root ) ) {
				return true;
			}
		}

		return false;
	}

	private function get_site_roots() {
		$roots = [ (string) get_option( 'home' ) ];

		if ( WPML_LANGUAGE_NEGOTIATION_TYPE_DOMAIN === (int) apply_filters( 'wpml_setting', 0, 'language_negotiation_type' ) ) {
			$domains = apply_filters( 'wpml_setting', [], 'language_domains' );
			$roots   = array_merge( $roots, is_array( $domains ) ? array_values( $domains ) : [] );
		}

		$roots = array_map(
			function ( $root ) {
				return trailingslashit( $this->strip_scheme( strtolower( (string) $root ) ) );
			},
			$roots
		);

		return array_values(
			array_filter(
				$roots,
				function ( $root ) {
					return '/' !== $root;
				}
			)
		);
	}

	private function strip_scheme( $url ) {
		return (string) preg_replace( '#^[a-z][a-z0-9+.\-]*://#i', '', $url );
	}

	protected function get_admin_referer_query() {
		$none     = [ 'path' => null, 'args' => [] ];
		$referer  = $this->lowercase_origin( (string) wp_get_raw_referer() );
		$adminUrl = $this->lowercase_origin( (string) admin_url() );

		if ( '' === $referer || '' === $adminUrl || 0 !== strpos( $referer, $adminUrl ) ) {
			return $none;
		}

		$screen = (string) strtok( substr( $referer, strlen( $adminUrl ) ), '#' );
		$parts  = explode( '?', $screen, 2 );
		$args   = [];
		if ( isset( $parts[1] ) ) {
			parse_str( $parts[1], $args );
		}

		return [ 'path' => $parts[0], 'args' => $args ];
	}

	private function lowercase_origin( $url ) {
		return (string) preg_replace_callback(
			'#^[a-z][a-z0-9+.\-]*://[^/?\#]*#i',
			function ( $origin ) {
				return strtolower( $origin[0] );
			},
			$url
		);
	}

	private function get_post_lang( $postId ) {
		global $wpml_post_translations;

		$postId = filter_var( $postId, FILTER_VALIDATE_INT );
		if ( ! $postId || $postId < 1 || ! $wpml_post_translations ) {
			return null;
		}

		$language = $wpml_post_translations->get_element_lang_code( $postId );

		return is_string( $language ) ? $language : null;
	}

	public function is_login_request() {
		if ( $this->is_login_page_request() ) {
			return true;
		}

		return ! is_user_logged_in() && $this->is_admin_screen_path( $this->get_request_path() );
	}

	public function is_login_page_request() {
		global $pagenow;

		if ( 'wp-login.php' === $pagenow ) {
			return true;
		}

		$path = $this->get_request_path();
		if ( $this->is_login_page_path( $path ) ) {
			return true;
		}

		$loginPath = $this->get_url_path( wp_login_url() );

		return '' !== $loginPath && $path === $loginPath;
	}

	private function is_login_page_path( $path ) {
		$path = rtrim( (string) $path, '/\\' );

		return 'wp-login.php' === $path || '/wp-login.php' === substr( $path, -13 );
	}

	private function is_admin_screen_path( $path ) {
		$adminPath = $this->get_url_path( admin_url() );
		if ( '' === $adminPath ) {
			return false;
		}

		$inAdmin = $path === $adminPath || 0 === strpos( $path, $adminPath . '/' );

		return $inAdmin && ! in_array( basename( $path ), [ 'admin-ajax.php', 'admin-post.php' ], true );
	}

	private function get_request_path() {
		return rtrim( (string) preg_replace( '#[?\#].*$#', '', $this->get_request_uri() ), '/\\' );
	}

	private function get_url_path( $url ) {
		$path = wp_parse_url( (string) $url, PHP_URL_PATH );

		return is_string( $path ) ? rtrim( $path, '/\\' ) : '';
	}

	public function show_hidden() {
		$queryVars = [];
		if ( isset( $_SERVER['QUERY_STRING'] ) ) {
			parse_str( $_SERVER['QUERY_STRING'], $queryVars );
		}

		$isReviewPostPage = (
			Obj::has( 'wpmlReviewPostType', $queryVars ) &&
			Obj::has( 'preview_id', $queryVars ) &&
			Obj::has( 'preview_nonce', $queryVars ) &&
			Obj::has( 'preview', $queryVars ) &&
			Obj::has( 'jobId', $queryVars ) &&
			Obj::has( 'returnUrl', $queryVars )
		);

		$isPostsListPage = false;
		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$uri = urldecode( $_SERVER['REQUEST_URI'] );
			if ( is_admin() && $uri && strpos( $uri, 'edit.php' ) !== false ) {
				$isPostsListPage = true;
			}
		}

		return ! did_action( 'init' )
			   || ( get_user_meta( get_current_user_id(), 'icl_show_hidden_languages', true )
					|| ( ( is_admin() || wpml_is_rest_request() ) && $this->isAdmin() ) )
			  || ( ( $isPostsListPage || $isReviewPostPage ) && ( $this->isAdmin() || $this->isEditor() ) )
			  || ( $isReviewPostPage && is_user_logged_in() && $this->isSubscriber() );
	}

	private function isAdmin() {
		return current_user_can( 'manage_options' );
	}

	private function isEditor() {
		$can = $this->getCaps();

		return $can['read'] && $can['publish'] && $can['edit'] && \WPML\LIB\WP\User::isTranslator();
	}

	private function isSubscriber() {
		return current_user_can( 'read' ) && \WPML\LIB\WP\User::isTranslator();
	}

	private function getCaps() {
		$canPublish   = current_user_can( 'publish_pages' ) || current_user_can( 'publish_posts' );
		$canRead      = current_user_can( 'read_private_pages' ) || current_user_can( 'read_private_posts' );
		$canEdit      = current_user_can( 'edit_posts' );

		return [
			'publish' => $canPublish,
			'read'    => $canRead,
			'edit'    => $canEdit,
		];
	}

	public function set_language_cookie( $lang_code ) {
		$this->cookieLanguage->set( $this->get_cookie_name(), $lang_code );
	}
}
