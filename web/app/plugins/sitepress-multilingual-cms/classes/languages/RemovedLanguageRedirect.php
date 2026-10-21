<?php

namespace WPML\Languages;

class RemovedLanguageRedirect {

	const MODE_DIRECTORY = 1;

	const MODE_PARAMETER = 3;

	const MAX_TYPES_PROBED = 12;

	private $url_converter;

	private $request_handler;

	private $sitepress;

	private $page_by_path;

	public function __construct( $url_converter, $request_handler, $sitepress = null, $page_by_path = null ) {
		$this->url_converter   = $url_converter;
		$this->request_handler = $request_handler;
		$this->sitepress       = $sitepress;
		$this->page_by_path    = $page_by_path;
	}

	public function target( $mode ) {
		$mode = (int) $mode;

		if ( self::MODE_DIRECTORY !== $mode && self::MODE_PARAMETER !== $mode ) {
			return false;
		}

		$uri   = (string) $this->request_handler->get_request_uri();
		$token = $this->requestedToken( $mode, $uri );
		$code  = RemovedLanguages::codeForUrlToken( $token );

		if ( '' === $code ) {
			return false;
		}

		if ( ! $this->isPageView() ) {
			return false;
		}

		$target = $this->counterpart( $mode, $uri, $token, $code );

		if ( ! is_string( $target ) || '' === $target ) {
			return false;
		}

		if ( $this->isSameAddress( $target, $uri )
			 || RemovedLanguages::codeForUrlToken( $this->requestedToken( $mode, $target ) ) === $code ) {
			return false;
		}

		return $target;
	}

	private function requestedToken( $mode, $uri ) {
		if ( self::MODE_PARAMETER === (int) $mode ) {
			return $this->sanitizeCode( $this->queryArg( $uri, 'lang' ) );
		}

		$path     = $this->pathWithoutInstallSubdir( $uri );
		$segments = explode( '/', trim( $path, '/' ) );

		return $this->sanitizeCode( reset( $segments ) );
	}

	private function isPageView() {
		if ( function_exists( 'is_admin' ) && is_admin() ) {
			return false;
		}

		if ( function_exists( 'wpml_is_cli' ) && wpml_is_cli() ) {
			return false;
		}

		if ( function_exists( 'wpml_is_ajax' ) && wpml_is_ajax() ) {
			return false;
		}

		if ( isset( $_REQUEST['rest_route'] ) ) {
			return false;
		}

		if ( function_exists( 'wpml_is_rest_request' ) && wpml_is_rest_request() ) {
			return false;
		}

		$path = (string) wpml_parse_url( (string) $this->request_handler->get_request_uri(), PHP_URL_PATH );
		$rest = (string) ( function_exists( 'rest_get_url_prefix' ) ? rest_get_url_prefix() : 'wp-json' );
		if ( '' !== $rest && '' !== $path && preg_match( '#(^|/)' . preg_quote( $rest, '#' ) . '(/|$)#', $path ) ) {
			return false;
		}

		return true;
	}

	private function counterpart( $mode, $uri, $token, $code ) {
		$home = $this->defaultLanguageHome();
		$post = $this->resolvePost( $this->strippedPath( $mode, $uri, $token ), $code );

		if ( ! $post ) {
			return $home;
		}

		$permalink = $this->defaultLanguagePermalink( $post );

		return $permalink ? $permalink : $home;
	}

	private function strippedPath( $mode, $uri, $token ) {
		$path = trim( $this->pathWithoutInstallSubdir( $uri ), '/' );

		if ( self::MODE_DIRECTORY === (int) $mode ) {
			$segments = explode( '/', $path );
			if ( isset( $segments[0] ) && $segments[0] === $token ) {
				array_shift( $segments );
			}
			$path = implode( '/', $segments );
		}

		$segments = array_values( array_filter( explode( '/', $path ), 'strlen' ) );
		$count    = count( $segments );

		if ( $count >= 2 && 'page' === $segments[ $count - 2 ] && ctype_digit( $segments[ $count - 1 ] ) ) {
			array_splice( $segments, -2 );
		}

		return implode( '/', $segments );
	}

	private function resolvePost( $path, $code ) {
		if ( '' === $path ) {
			return null;
		}

		$segments = explode( '/', $path );
		$leaf     = (string) end( $segments );

		foreach ( $this->candidateTypes() as $type ) {
			$needle = $this->isHierarchical( $type ) ? $path : $leaf;
			$post   = $this->pageByPath()->get( $needle, $code, OBJECT, $type );

			if ( is_object( $post ) && ! empty( $post->ID ) ) {
				return $post;
			}
		}

		return null;
	}

	private function candidateTypes() {
		$sitepress = $this->sitepress();

		if ( ! is_object( $sitepress ) || ! method_exists( $sitepress, 'get_translatable_documents' ) ) {
			return array();
		}

		$types = array_keys( (array) $sitepress->get_translatable_documents( false ) );
		$first = array_values( array_intersect( array( 'page', 'post' ), $types ) );
		$rest  = array_values( array_diff( $types, array( 'page', 'post' ) ) );

		return array_slice( array_merge( $first, $rest ), 0, self::MAX_TYPES_PROBED );
	}

	private function isHierarchical( $type ) {
		return function_exists( 'is_post_type_hierarchical' ) && is_post_type_hierarchical( $type );
	}

	private function defaultLanguagePermalink( $post ) {
		$sitepress = $this->sitepress();

		if ( ! is_object( $sitepress ) || empty( $post->post_type ) ) {
			return null;
		}

		$element_type = 'post_' . $post->post_type;
		$trid         = $sitepress->get_element_trid( (int) $post->ID, $element_type );

		if ( ! $trid ) {
			return null;
		}

		$default      = (string) $sitepress->get_default_language();
		$translations = (array) $sitepress->get_element_translations( $trid, $element_type );

		if ( empty( $translations[ $default ]->element_id ) ) {
			return null;
		}

		$counterpart_id = (int) $translations[ $default ]->element_id;

		if ( ! in_array( get_post_status( $counterpart_id ), array( 'publish', 'inherit' ), true ) ) {
			return null;
		}

		$sitepress->switch_lang( $default );
		try {
			$permalink = get_permalink( $counterpart_id );
		} finally {
			$sitepress->switch_lang();
		}

		return is_string( $permalink ) && '' !== $permalink ? $permalink : null;
	}

	private function defaultLanguageHome() {
		$home      = (string) $this->url_converter->get_abs_home();
		$sitepress = $this->sitepress();

		if ( ! is_object( $sitepress ) ) {
			return $home;
		}

		$converted = $this->url_converter->convert_url( $home, $sitepress->get_default_language() );

		return is_string( $converted ) && '' !== $converted ? $converted : $home;
	}

	private function isSameAddress( $target, $uri ) {
		$target_path = trim( (string) wpml_parse_url( $target, PHP_URL_PATH ), '/' );
		$uri_path    = trim( (string) wpml_parse_url( $uri, PHP_URL_PATH ), '/' );

		if ( $target_path !== $uri_path ) {
			return false;
		}

		return (string) wpml_parse_url( $target, PHP_URL_QUERY ) === (string) wpml_parse_url( $uri, PHP_URL_QUERY );
	}

	private function pathWithoutInstallSubdir( $uri ) {
		$path = (string) wpml_parse_url( $uri, PHP_URL_PATH );

		if ( '' === $path ) {
			$path = (string) strtok( $uri, '?' );
		}

		$subdir = (string) wpml_parse_url( (string) $this->url_converter->get_abs_home(), PHP_URL_PATH );

		if ( '' !== trim( $subdir, '/' ) ) {
			$path = (string) preg_replace( '#^' . preg_quote( rtrim( $subdir, '/' ), '#' ) . '#', '', $path );
		}

		return $path;
	}

	private function queryArg( $uri, $name ) {
		$query = (string) wpml_parse_url( $uri, PHP_URL_QUERY );

		if ( '' === $query ) {
			return '';
		}

		$args = array();
		parse_str( $query, $args );

		return isset( $args[ $name ] ) && is_scalar( $args[ $name ] ) ? (string) $args[ $name ] : '';
	}

	private function sanitizeCode( $candidate ) {
		if ( ! is_scalar( $candidate ) ) {
			return '';
		}

		$candidate = (string) $candidate;

		return preg_match( '/^[A-Za-z0-9_-]{1,20}$/', $candidate ) ? $candidate : '';
	}

	private function sitepress() {
		if ( null === $this->sitepress ) {
			global $sitepress;
			$this->sitepress = $sitepress;
		}

		return $this->sitepress;
	}

	private function pageByPath() {
		if ( null === $this->page_by_path ) {
			global $wpdb;
			$this->page_by_path = new \WPML_Get_Page_By_Path( $wpdb, $this->sitepress(), new \WPML_Debug_BackTrace( null, 10 ) );
		}

		return $this->page_by_path;
	}
}
