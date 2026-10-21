<?php

class WPML_Frontend_Post_Actions extends WPML_Post_Translation {

	public function init() {
		parent::init ();
		if ( $this->is_setup_complete() ) {
			add_action( 'delete_post', [ $this, 'delete_post_actions' ] );
			add_action( 'wp_trash_post', [ $this, 'trashed_post_actions' ] );
			add_action( 'untrashed_post', [ $this, 'untrashed_post_actions' ] );
		}
	}

	public function delete_post_actions( $post_id, $keep_db_entries = false ) {
		if ( ! $this->is_wpml_post( $post_id ) ) {
			return;
		}

		parent::delete_post_actions( $post_id, $keep_db_entries );
	}

	public function untrashed_post_actions( $post_id ) {
		if ( ! $this->is_wpml_post( $post_id ) ) {
			return;
		}

		parent::untrashed_post_actions( $post_id );
	}

	private function is_wpml_post( $post_id ) {
		return $this->is_translated_type( get_post_type( $post_id ) ) || (bool) $this->get_element_trid( $post_id );
	}

	function get_save_post_trid( $post_id, $post_status ) {
		$trid = $this->get_element_trid( $post_id );

		if (
			! $trid
			&& ! empty( $_GET['wpml_editor'] )
			&& isset( $_GET['trid'], $_GET['lang'] )
			&& wpml_is_rest_request()
		) {
			global $sitepress;
			$maybe_trid = (int) $_GET['trid'];
			$maybe_lang = \WPML\Language\RequestedLanguage::forPrivileged( sanitize_text_field( wp_unslash( $_GET['lang'] ) ) );
			if (
				$maybe_trid
				&& null !== $maybe_lang
				&& SitePress::get_source_language_by_trid( $maybe_trid )
				&& $this->current_user_can_join_translation_group(
					$maybe_trid,
					'post_' . get_post_type( $post_id ),
					$maybe_lang
				)
			) {
				$trid = $maybe_trid;
			}
		}

		return $trid;
	}

	public function get_save_post_lang( $post_id, $sitepress ) {
		if (
			! empty( $_GET['wpml_editor'] )
			&& isset( $_GET['lang'] )
			&& wpml_is_rest_request()
			&& ! $this->get_element_lang_code( $post_id )
		) {
			$maybe_lang = \WPML\Language\RequestedLanguage::forPrivileged( sanitize_text_field( wp_unslash( $_GET['lang'] ) ) );
			if ( null !== $maybe_lang ) {
				return apply_filters( 'wpml_save_post_lang', $maybe_lang );
			}
		}

		return parent::get_save_post_lang( $post_id, $sitepress );
	}

	public function save_post_actions( $pidd, $post ) {
		global $sitepress;

		$this->defer_term_counting();
		if ( ! $post ) {
			$post = get_post( $pidd );
		}

		$http_referer = new WPML_URL_HTTP_Referer( new WPML_Rest( new WP_Http() ) );
		if ( ! $this->has_save_post_action( $post ) || $http_referer->is_rest_request_called_from_post_edit_page() ) {
			return;
		}
		$default_language       = $sitepress->get_default_language();
		$post_vars              = $this->get_post_vars( $post );
		$post_id                = isset( $post_vars['post_ID'] ) ? $post_vars['post_ID']
			: $pidd;
		$language_code          = $this->get_save_post_lang( $post_id, $sitepress );
		$trid                   = $this->get_save_post_trid( $post_id, $post->post_status );
		$source_language = $this->get_save_post_source_lang( $trid, $language_code, $default_language );
		$this->after_save_post( $trid, $post_vars, $language_code, $source_language );
	}

	protected function get_save_post_source_lang( $trid, $language_code, $default_language ) {
		$post_id = $this->get_element_id( $language_code, $trid );

		return $post_id ? $this->get_source_lang_code( $post_id ) : null;
	}
}