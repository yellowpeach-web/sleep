<?php

namespace WPML\TM\PostEditScreen;

use IWPML_Backend_Action;
use SitePress;
use WP_Post;
use WPML_Post_Status_Display;
use WPML_Super_Globals_Validation;
use WPML_WP_API;

class StaleAddTranslationGuard implements IWPML_Backend_Action {

	const STALE_PARAM = 'wpml_stale_add_translation';

	const OPENED_TRANSIENT_PREFIX = 'wpml_add_translation_opened_';

	const OPENED_LIFETIME = 604800;

	private $request;

	private $sitepress;

	private $wp_api;

	public function __construct(
		?WPML_Super_Globals_Validation $request = null,
		?SitePress $sitepress = null,
		?WPML_WP_API $wp_api = null
	) {
		$this->request   = $request ?: new WPML_Super_Globals_Validation();
		$this->sitepress = $sitepress ?: $GLOBALS['sitepress'];
		$this->wp_api    = $wp_api ?: new WPML_WP_API();
	}

	public function add_hooks() {
		add_action( 'load-post-new.php', [ $this, 'refuse_stale_open' ] );
		add_action( 'load-edit.php', [ $this, 'arm_notice' ] );
	}

	public function refuse_stale_open() {
		$trid = $this->requested_trid();
		$lang = $this->requested_language();

		if ( $trid <= 0 || '' === $lang ) {
			return;
		}

		$rendered_at = (int) $this->request->get( WPML_Post_Status_Display::RENDERED_AT_PARAM, FILTER_SANITIZE_NUMBER_INT );

		if ( $rendered_at > 0 && $this->slot_changed_since( $trid, $lang, $rendered_at ) ) {
			$this->wp_api->wp_safe_redirect( $this->posts_list_url( $lang ), 302 );

			return;
		}

		add_action( 'add_meta_boxes', [ $this, 'record_open' ], 1, 2 );

		if ( $rendered_at > 0 ) {
			add_action( 'admin_print_footer_scripts', [ $this, 'print_address_bar_cleanup' ] );
		}
	}

	public function record_open( $post_type, $post = null ) {
		$trid = $this->requested_trid();
		$lang = $this->requested_language();

		if (
			$trid <= 0
			|| '' === $lang
			|| ! $post instanceof WP_Post
			|| empty( $post->ID )
			|| 'auto-draft' !== $post->post_status
			|| (int) $post->post_author !== (int) get_current_user_id()
		) {
			return;
		}

		set_transient( self::opened_key( $trid, $lang ), time(), self::OPENED_LIFETIME );
	}

	public function print_address_bar_cleanup() {
		echo '<script>'
			. '(function(){try{var u=new URL(window.location.href);'
			. "if(u.searchParams.has('" . esc_js( WPML_Post_Status_Display::RENDERED_AT_PARAM ) . "')){"
			. "u.searchParams.delete('" . esc_js( WPML_Post_Status_Display::RENDERED_AT_PARAM ) . "');"
			. 'window.history.replaceState(window.history.state,"",u.toString());}}catch(e){}})();'
			. '</script>';
	}

	public function arm_notice() {
		if ( '' === $this->refused_language_name() ) {
			return;
		}

		add_action( 'admin_notices', [ $this, 'print_notice' ] );
		add_filter( 'removable_query_args', [ $this, 'add_removable_query_arg' ] );

		if ( isset( $_SERVER['REQUEST_URI'] ) && is_string( $_SERVER['REQUEST_URI'] ) ) {
			$_SERVER['REQUEST_URI'] = remove_query_arg( self::STALE_PARAM, esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) );
		}
	}

	public function add_removable_query_arg( $args ) {
		$args   = is_array( $args ) ? $args : [];
		$args[] = self::STALE_PARAM;

		return $args;
	}

	public function print_notice() {
		$language_name = $this->refused_language_name();

		if ( '' === $language_name ) {
			return;
		}

		echo '<div class="notice notice-warning"><p>'
			. esc_html(
				sprintf(
					/* translators: Shown on the posts list after a "+" pressed on an outdated page. %s: the name of the language (French). */
					__( 'The %s translation was started after this page was loaded. This list is up to date.', 'sitepress' ),
					$language_name
				)
			)
			. '</p></div>';
	}

	private function slot_changed_since( $trid, $lang, $rendered_at ) {
		if ( $this->sitepress->post_translations()->get_element_id( $lang, $trid ) ) {
			return true;
		}

		return $this->last_open( $trid, $lang ) >= $rendered_at;
	}

	private function last_open( $trid, $lang ) {
		return (int) get_transient( self::opened_key( $trid, $lang ) );
	}

	private static function opened_key( $trid, $lang ) {
		return self::OPENED_TRANSIENT_PREFIX . (int) $trid . '_' . $lang;
	}

	private function posts_list_url( $lang ) {
		$args = [ 'post_type' => $this->requested_post_type() ];

		$source_lang = (string) $this->request->get( 'source_lang', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( '' !== $source_lang ) {
			$args['lang'] = $source_lang;
		}

		$args[ self::STALE_PARAM ] = $lang;

		return admin_url( 'edit.php?' . http_build_query( $args ) );
	}

	private function refused_language_name() {
		$lang = (string) $this->request->get( self::STALE_PARAM, FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( '' === $lang ) {
			return '';
		}

		$details = $this->sitepress->get_language_details( $lang );

		return is_array( $details ) && ! empty( $details['display_name'] ) ? (string) $details['display_name'] : '';
	}

	private function requested_post_type() {
		$post_type = (string) $this->request->get( 'post_type', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		return '' !== $post_type && post_type_exists( $post_type ) ? $post_type : 'post';
	}

	private function requested_trid() {
		return (int) $this->request->get( 'trid', FILTER_SANITIZE_NUMBER_INT );
	}

	private function requested_language() {
		return (string) $this->request->get( 'lang', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
	}
}
