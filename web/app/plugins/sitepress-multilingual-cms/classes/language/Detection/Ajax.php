<?php

namespace WPML\Language\Detection;

use WPML\FP\Maybe;
use WPML\FP\Obj;
use WPML\FP\Lst;
use WPML\FP\Str;
use WPML\FP\Fns;
use WPML\Comments\Counts\FeatureSwitch;
use \WPML_Request;

class Ajax extends WPML_Request {

	const COMMENT_MODERATION_ACTIONS = [ 'dim-comment', 'delete-comment', 'replyto-comment' ];

	const COMMENTS_LIST_CLASS = 'WP_Comments_List_Table';

	const COMMENT_SCREEN_LANG_PARAM = 'wpml_comment_screen_lang';

	private $language_from_screen = false;

	public function get_requested_lang() {
		$this->language_from_screen = false;

		return Maybe::of( $_REQUEST )
					->map( Obj::prop( 'lang' ) )
					->filter( Lst::includes( Fns::__, $this->active_languages ) )
					->map( 'sanitize_text_field' )
					->getOrElse(
						function () {
							return $this->get_media_grid_all_languages()
								?: $this->get_comment_moderation_all_languages()
								?: $this->get_comment_screen_lang()
								?: $this->get_referer_lang()
								?: $this->get_frontend_referer_lang()
								?: $this->get_cookie_lang();
						}
					);
	}

	private function get_media_grid_all_languages() {
		if ( 'query-attachments' !== $this->get_ajax_action() ) {
			return null;
		}

		$referer = wp_get_raw_referer();
		if ( ! $referer ) {
			return null;
		}

		$query = [];
		parse_str( (string) wpml_parse_url( $referer, PHP_URL_QUERY ), $query );

		return isset( $query['lang'] ) && 'all' === $query['lang'] ? 'all' : null;
	}

	private function get_comment_moderation_all_languages() {
		if ( ! $this->is_comment_list_ajax_action() ) {
			return null;
		}

		$referer = $this->get_admin_referer_query();
		if ( null === $referer['path'] || ! isset( $referer['args']['lang'] ) || 'all' !== $referer['args']['lang'] ) {
			return null;
		}

		return $this->is_comment_counts_per_language_on() ? 'all' : null;
	}

	private function get_comment_screen_lang() {
		if ( ! $this->is_comment_list_ajax_action() ) {
			return null;
		}

		$language = $this->get_comment_screen_lang_param();
		if ( ! is_string( $language ) || ! in_array( $language, $this->active_languages, true ) || ! $this->is_comment_counts_per_language_on() ) {
			return null;
		}

		$this->language_from_screen = true;

		return $language;
	}

	public function is_language_from_referer() {
		return $this->language_from_screen || parent::is_language_from_referer();
	}

	protected function get_comment_screen_lang_param() {
		$language = filter_input( INPUT_POST, self::COMMENT_SCREEN_LANG_PARAM );
		if ( null === $language ) {
			$language = filter_input( INPUT_GET, self::COMMENT_SCREEN_LANG_PARAM );
		}

		return is_string( $language ) ? $language : null;
	}

	private function is_comment_list_ajax_action() {
		$action = $this->get_request_ajax_action();

		return in_array( $action, self::COMMENT_MODERATION_ACTIONS, true )
			|| ( 'fetch-list' === $action && self::COMMENTS_LIST_CLASS === $this->get_fetch_list_class() );
	}

	private function is_comment_counts_per_language_on() {
		global $sitepress;

		return FeatureSwitch::isOn()
			&& $sitepress instanceof \SitePress
			&& $sitepress->is_setup_complete();
	}

	protected function get_request_ajax_action() {
		$action = filter_input( INPUT_POST, 'action' );
		if ( null === $action ) {
			$action = filter_input( INPUT_GET, 'action' );
		}

		return is_string( $action ) ? sanitize_key( $action ) : '';
	}

	protected function get_fetch_list_class() {
		$listArgs = filter_input( INPUT_GET, 'list_args', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );

		return is_array( $listArgs ) && isset( $listArgs['class'] ) && is_string( $listArgs['class'] )
			? $listArgs['class']
			: '';
	}

	protected function get_ajax_action() {
		return (string) filter_input( INPUT_POST, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
	}

	protected function get_cookie_name() {
		return $this->cookieLanguage->getAjaxCookieName( $this->is_admin_action_from_referer() );
	}

	private function is_admin_action_from_referer() {
		$adminSlug = basename( trim( admin_url(), '/' ) );

		return (bool) Maybe::of( $_SERVER )
						   ->map( Obj::prop( 'HTTP_REFERER' ) )
						   ->map( Str::pos( '/' . $adminSlug . '/' ) )
						   ->getOrElse( false );
	}
}
