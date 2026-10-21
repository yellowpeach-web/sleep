<?php

use WPML\FP\Relation;
use \WPML\FP\Str;
use \WPML\SuperGlobals\Server;

class WPML_URL_Filters {
	const POST_TYPE_LINK_SLUG_FILTER = 'wpml_post_type_link_translate_slug';

	private $sitepress;

	private $post_translation;

	private $canonicals;

	private $url_converter;

	private $debug_backtrace;

	private $ls_languages_status;

	private $post_type_link_hook_priority;

	private $slash_management;

	private $permalink_hops_in_progress = [];

	private $logged_permalink_reentries = [];

	public function __construct(
		&$post_translation,
		&$url_converter,
		WPML_Canonicals $canonicals,
		&$sitepress,
		WPML_Debug_BackTrace $debug_backtrace,
		?WPML_Get_LS_Languages_Status $ls_languages_status = null
	) {
		$this->sitepress        = &$sitepress;
		$this->post_translation = &$post_translation;

		$this->url_converter                = &$url_converter;
		$this->canonicals                   = $canonicals;
		$this->debug_backtrace              = $debug_backtrace;
		$this->ls_languages_status          = $ls_languages_status
			? $ls_languages_status
			: WPML_Get_LS_Languages_Status::get_instance();

		$urls = $this->sitepress->get_setting( 'urls' );
		if ( $this->urls_use_root( $urls ) === true || WPML_Root_Page::is_root_page_in_setup( $urls ) ) {
			WPML_Root_Page::init();
		}
		$this->add_hooks();
	}

	private function add_hooks() {
		if ( $this->frontend_uses_root() === true ) {
			add_filter( 'page_link', array( $this, 'page_link_filter_root' ), 1, 2 );
		} else {
			add_filter( 'page_link', array( $this, 'page_link_filter' ), 1, 2 );
		}

		$this->add_global_hooks();
		if ( $this->has_wp_get_canonical_url() ) {
			add_filter( 'get_canonical_url', array( $this, 'get_canonical_url_filter' ), 1, 2 );
		}

		add_action( 'current_screen', [ $this, 'permalink_options_home_url' ] );
	}

	public function add_global_hooks() {
		if ( null === $this->post_type_link_hook_priority ) {
			$this->post_type_link_hook_priority = intval(
				apply_filters_deprecated(
					'wpml_post_type_link_priority',
					[ 1 ],
					'6.0.0',
					'',
					'String Translation no longer hooks post_type_link. This filter now only moves WPML\'s own post_type_link callback, and it will be removed.'
				)
			) + 1;
		}

		add_filter( 'home_url', [ $this, 'home_url_filter' ], - 10, 4 );
		add_filter( 'post_link', [ $this, 'permalink_filter' ], 1, 2 );
		add_filter( 'attachment_link', [ $this, 'permalink_filter' ], 1, 2 );
		add_filter( 'post_type_link', [ $this, 'post_type_link_filter' ], $this->post_type_link_hook_priority, 4 );
		add_filter( 'wpml_filter_link', [ $this, 'permalink_filter' ], 1, 2 );
		add_filter( 'get_edit_post_link', [ $this, 'get_edit_post_link' ], 1, 3 );
		add_filter( 'oembed_request_post_id', [ $this, 'embedded_front_page_id_filter' ], 1, 2 );
		add_filter( 'post_embed_url', [ $this, 'fix_post_embedded_url' ], 1, 1 );
	}

	public function remove_global_hooks() {
		remove_filter( 'oembed_request_post_id', [ $this, 'embedded_front_page_id_filter' ], 1 );
		remove_filter( 'post_embed_url', [ $this, 'fix_post_embedded_url' ], 1 );
		remove_filter( 'get_edit_post_link', [ $this, 'get_edit_post_link' ], 1 );
		remove_filter( 'wpml_filter_link', [ $this, 'permalink_filter' ], 1 );
		remove_filter( 'post_type_link', [ $this, 'post_type_link_filter' ], $this->post_type_link_hook_priority );
		remove_filter( 'attachment_link', [ $this, 'permalink_filter' ], 1 );
		remove_filter( 'post_link', [ $this, 'permalink_filter' ], 1 );

		remove_filter( 'home_url', [ $this, 'home_url_filter' ], - 10 );
	}

	public function embedded_front_page_id_filter( $post_id, $url ) {
		if ( ! $post_id && $this->is_front_page( $url ) ) {
			$page_on_front = get_option( 'page_on_front' );

			if ( $page_on_front && get_post( $page_on_front ) instanceof WP_Post ) {
				$post_id = (int) $page_on_front;
			}
		}

		return $post_id;
	}

	public function fix_post_embedded_url( $embedded_url ) {
		$query = wpml_parse_url( $embedded_url, PHP_URL_QUERY );
		$embed = user_trailingslashit( 'embed' );
		if ( Str::includes( $embed, $query ) ) {
			$embedded_url = Str::split( '?', $embedded_url )[0] . $embed . '?' .
				   untrailingslashit( Str::replace( $embed, '', $query ) );
		}

		return $embedded_url;
	}

	public function get_edit_post_link( $link, $id, $context = 'display' ) {
		if ( $id && (bool) ( $lang = $this->post_translation->get_element_lang_code( $id ) ) === true ) {
			$link .= ( 'display' === $context ? '&amp;' : '&' ) . 'lang=' . $lang;
			if ( ! did_action( 'wpml_pre_status_icon_display' ) ) {
				do_action( 'wpml_pre_status_icon_display' );
			}
			$resolved = apply_filters( 'wpml_link_to_translation', $link, $id, $lang, $this->post_translation->get_element_trid( $id ) );

			if ( '' !== (string) $resolved ) {
				$link = $resolved;
			}
		}

		return $link;
	}

	public function permalink_filter_root( $link, $pid ) {
		$pid  = is_object( $pid ) ? $pid->ID : $pid;
		$link = $this->sitepress->get_root_page_utils()->get_root_page_id() != $pid
				? $this->permalink_filter( $link, $pid ) : $this->filter_root_permalink( $link );

		return $link;
	}

	public function page_link_filter_root( $link, $pid ) {
		$pid  = is_object( $pid ) ? $pid->ID : $pid;
		$link = $this->sitepress->get_root_page_utils()->get_root_page_id() != $pid
			? $this->page_link_filter( $link, $pid ) : $this->filter_root_permalink( $link );

		return $link;
	}

	public function filter_root_permalink( $url ) {
		$root_page_utils = $this->sitepress->get_root_page_utils();
		if ( $root_page_utils->get_root_page_id() > 0 && $root_page_utils->is_url_root_page( $url ) ) {
			if ( strpos( $url, 'rest_route=' ) !== false ) {
				return $url;
			}

			$url_parts = wpml_parse_url( $url );
			$query     = isset( $url_parts['query'] ) ? $url_parts['query'] : '';
			$path      = isset( $url_parts['path'] ) ? $url_parts['path'] : '';
			$slugs     = array_filter( explode( '/', $path ) );
			$last_slug = array_pop( $slugs );
			$new_url   = $this->url_converter->get_abs_home();
			$new_url   = is_numeric( $last_slug ) ? trailingslashit( trailingslashit( $new_url ) . $last_slug ) : $new_url;
			$query     = $this->unset_page_query_vars( $query );
			$new_url   = trailingslashit( $new_url );
			$url       = (bool) $query === true ? trailingslashit( $new_url ) . '?' . $query : $new_url;
		}

		return $url;
	}

	public function permalink_filter( $link, $post ) {
		return $this->filter_permalink( $link, $post, null );
	}

	public function post_type_link_filter( $link, $post, $leavename = false, $sample = false ) {
		$translate_slug = function ( $link ) use ( $post, $leavename, $sample ) {
			return apply_filters( self::POST_TYPE_LINK_SLUG_FILTER, $link, $post, $leavename, $sample );
		};

		return $this->filter_permalink( $link, $post, $translate_slug );
	}

	private function filter_permalink( $link, $post, $translate_slug ) {
		if ( ! $post ) {
			return $link;
		}

		$post_id = $post;

		if ( is_object( $post ) ) {
			$post_id = $post->ID;
		}

		if ( $post_id < 1 ) {
			return $link;
		}

		$canonical_url = $this->canonicals->permalink_filter( $link, $post_id );
		if ( $canonical_url ) {
			return $canonical_url;
		}

		$post_element = new WPML_Post_Element( $post_id, $this->sitepress );
		if (
			! is_wp_error( $post_element->get_wp_element_type() )
			&& ! $this->is_display_as_translated_mode( $post_element )
			&& $post_element->is_translatable()
		) {
			$link = $this->get_translated_permalink( $link, $post_id, $post_element, $translate_slug );
		} else {
			$link = $this->translate_slug( $link, $translate_slug );
		}

		return $this->url_converter->get_strategy()->fix_trailingslashit( $link );
	}

	private function translate_slug( $link, $translate_slug ) {
		return $translate_slug ? $translate_slug( $link ) : $link;
	}

	public function page_link_filter( $link, $post ) {
		return $this->permalink_filter( $link, $post );
	}

	private function has_wp_get_canonical_url() {
		return $this->sitepress->get_wp_api()->function_exists( 'wp_get_canonical_url' );
	}

	public function get_canonical_url_filter( $canonical_url, $post ) {
		return $this->canonicals->get_canonical_url( $canonical_url, $post, $this->get_request_language() );
	}

	public function permalink_options_home_url( $current_screen ) {
		if ( Relation::propEq( 'id', 'options-permalink', $current_screen ) ) {
			add_filter( 'wpml_get_home_url', [ $this, 'get_home_url_without_language' ], 9, 2 );
			add_filter( 'wpml_get_home_url', 'untrailingslashit' );
		}
	}

	public function get_home_url_without_language( $home_url, $url ) {
		return $url;
	}

	public function home_url_filter( $url, $path, $orig_scheme, $blog_id ) {
		$language_negotiation_type = (int) $this->sitepress->get_setting( 'language_negotiation_type' );

		$context = new WPML_Home_Url_Filter_Context(
			$language_negotiation_type,
			$orig_scheme,
			$this->debug_backtrace
		);

		if ( $context->is_rest_request() ) {
			return $url;
		}

		$url_language = $this->get_request_language();

		if ( 'relative' === $orig_scheme ) {
			$home_url = $this->url_converter->get_home_url_relative( $url, $url_language );
		} else {
			$home_url = $this->url_converter->convert_url( $url, $url_language );
			$home_url = $this->keep_trailing_slash_of_path( $home_url, $url, $path );
		}

		$home_url = apply_filters( 'wpml_get_home_url', $home_url, $url, $path, $orig_scheme, $blog_id );

		if ( $home_url === $url || $context->is_called_from_guarded_function() ) {
			return $url;
		}

		return $home_url;
	}

	private function keep_trailing_slash_of_path( $home_url, $url, $path ) {
		if ( $home_url === $url || ! $path || ! is_string( $path ) ) {
			return $home_url;
		}

		if ( ! $this->slash_management ) {
			$this->slash_management = new WPML_Slash_Management();
		}

		return $this->slash_management->match_path_trailing_slash_to_reference( $home_url, $url );
	}

	public function frontend_uses_root() {
		return $this->urls_use_root( $this->sitepress->get_setting( 'urls' ) );
	}

	private function urls_use_root( $urls ) {
		if ( is_admin() ) {
			$uses_root = isset( $urls['root_page'], $urls['show_on_root'] )
						 && ! empty( $urls['directory_for_default_language'] )
						 && ( in_array( $urls['show_on_root'], array( 'page', 'html_file' ) ) );
		} else {
			$uses_root = isset( $urls['root_page'], $urls['show_on_root'] )
						 && ! empty( $urls['directory_for_default_language'] )
						 && ( ( $urls['root_page'] > 0 && 'page' === $urls['show_on_root'] )
							  || ( $urls['root_html_file_path'] && 'html_file' === $urls['show_on_root'] ) );
		}

		return $uses_root;
	}

	private function get_permalink_filter_lang( $post_id ) {
		if ( isset( $_POST['action'] ) && $_POST['action'] === 'sample-permalink' ) {
			$code = $this->get_language_from_url();
			$code = $code
				? $code
				: ( ! isset( $_SERVER['HTTP_REFERER'] )
					? $this->sitepress->get_default_language()
					: $this->url_converter->get_language_from_url( $_SERVER['HTTP_REFERER'] ) );
		} else {
			$code = $this->post_translation->get_element_lang_code( $post_id );
		}

		return $code;
	}

	private function unset_page_query_vars( $query ) {
		parse_str( (string) $query, $query_parts );
		foreach ( array( 'p', 'page_id', 'page', 'pagename', 'page_name', 'attachement_id' ) as $part ) {
			if ( isset( $query_parts[ $part ] ) && ! ( $part === 'page_id' && ! empty( $query_parts['preview'] ) ) ) {
				unset( $query_parts[ $part ] );
			}
		}

		return http_build_query( $query_parts );
	}

	private function get_language_from_url( $return_default_language_if_missing = false ) {
		$query_string_argument = '';
		$language              = '';
		if ( $return_default_language_if_missing ) {
			$language = $this->sitepress->get_current_language();
		}
		if ( array_key_exists( 'wpml_lang', $_GET ) ) {
			$query_string_argument = 'wpml_lang';
		} elseif ( array_key_exists( 'lang', $_GET ) ) {
			$query_string_argument = 'lang';
		}
		if ( '' !== $query_string_argument ) {
			$language = filter_var( $_GET[ $query_string_argument ], FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		}

		return $language;
	}

	public function get_translated_permalink( $link, $post, $post_element, $translate_slug = null ) {
		$code                      = $this->get_permalink_filter_lang( $post );
		$force_translate_permalink = apply_filters( 'wpml_force_translated_permalink', false );
		$translated_post_id        = null;

		if ( ( ! is_admin() || wp_doing_ajax() || wpml_is_ajax() || $force_translate_permalink )
			 && ( ! $this->sitepress->get_wp_api()->is_a_REST_request() || $force_translate_permalink )
		) {
			$translated_post_id = $this->get_permalink_translation_id( $post_element );
		}

		$post_id = (int) $post_element->get_element_id();

		if ( $translated_post_id && (int) $translated_post_id !== $post_id ) {
			$link = $this->get_permalink_of_translation( $link, $code, $post_id, $translated_post_id, $translate_slug );
		} else {
			$link = $this->url_converter->get_strategy()->convert_url_string( $this->translate_slug( $link, $translate_slug ), $code );
		}
		if ( $this->sitepress->get_wp_api()->is_feed() ) {
			$link = str_replace( '&lang=', '&#038;lang=', $link );
		}

		return $link;
	}

	private function get_permalink_of_translation( $link, $code, $post_id, $translated_post_id, $translate_slug = null ) {
		$current_language = (string) $this->sitepress->get_current_language();
		$key              = get_current_blog_id() . '|' . $post_id . '|' . $current_language;

		if ( isset( $this->permalink_hops_in_progress[ $key ] ) ) {
			$this->report_blocked_permalink_reentry( $key, $post_id, $current_language, (int) $translated_post_id );

			return $this->url_converter->get_strategy()->convert_url_string( $this->translate_slug( $link, $translate_slug ), $code );
		}

		$this->permalink_hops_in_progress[ $key ] = true;
		try {
			return get_permalink( $translated_post_id );
		} finally {
			unset( $this->permalink_hops_in_progress[ $key ] );
		}
	}

	private function report_blocked_permalink_reentry( $key, $post_id, $current_language, $translated_post_id ) {
		do_action( 'wpml_permalink_reentry_blocked', $post_id, $current_language, $translated_post_id );

		$wp_api = $this->sitepress->get_wp_api();
		if ( isset( $this->logged_permalink_reentries[ $key ] ) || ! $wp_api->constant( 'WP_DEBUG_LOG' ) ) {
			return;
		}

		$this->logged_permalink_reentries[ $key ] = true;
		$wp_api->error_log(
			sprintf(
				'WPML: blocked a permalink loop for post %d (current language %s, target post %d).',
				$post_id,
				$current_language,
				$translated_post_id
			)
		);
	}

	public function get_translated_page_link( $link, $post_id, $post_element ) {
		$code = $this->get_permalink_filter_lang( $post_id );
		if ( ! is_admin() && $this->should_use_permalink_of_post_translation( $post_element ) ) {
			$link = get_page_link( $this->get_translated_post_id_for_current_language( $post_element ) );
		} else {
			$link = $this->url_converter->get_strategy()->convert_url_string( $link, $code );
		}
		if ( $this->sitepress->get_wp_api()->is_feed() ) {
			$link = str_replace( '&lang=', '&#038;lang=', $link );
		}

		return $link;
	}

	public function get_request_language() {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
		$server_name = strpos( $request_uri, '/' ) === 0
			? untrailingslashit( Server::getServerName() ) : trailingslashit( Server::getServerName() );
		$url_snippet = $server_name . $request_uri;

		return $this->url_converter->get_language_from_url( $url_snippet );
	}

	private function should_use_permalink_of_post_translation( WPML_Post_Element $post_element ) {
		return (bool) $this->get_permalink_translation_id( $post_element );
	}

	private function get_permalink_translation_id( WPML_Post_Element $post_element ) {
		if ( $this->sitepress->get_setting( 'auto_adjust_ids' )
			 && $post_element->get_language_code() !== $this->sitepress->get_current_language()
			 && apply_filters( 'wpml_use_permalink_of_post_translation', true, $post_element )
		) {
			return $this->get_translated_post_id_for_current_language( $post_element ) ?: null;
		}

		return null;
	}

	private function is_display_as_translated_mode( WPML_Post_Element $post_element ) {
		return $post_element->is_display_as_translated() &&
			   $post_element->get_language_code() == $this->sitepress->get_default_language() &&
			   ! $this->get_translated_post_id_for_current_language( $post_element ) &&
			   ! $this->ls_languages_status->is_getting_ls_languages();
	}

	private function get_translated_post_id_for_current_language( WPML_Post_Element $post_element ) {
		$post_id          = $post_element->get_element_id();
		$current_language = $this->sitepress->get_current_language();

		return $this->post_translation->element_id_in( $post_id, $current_language );
	}

	private function is_front_page( $url ) {
		return $this->canonicals->get_general_canonical_url( $url ) === home_url() && 'page' === get_option( 'show_on_front' );
	}
}
