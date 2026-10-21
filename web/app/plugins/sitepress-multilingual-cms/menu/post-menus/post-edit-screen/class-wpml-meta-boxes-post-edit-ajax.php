<?php

use WPML\TranslationRoles\LangPairPermissions;

class WPML_Meta_Boxes_Post_Edit_Ajax implements IWPML_Action {

	const ACTION_GET_META_BOXES = 'wpml_get_meta_boxes_html';
	const ACTION_GET_ADMIN_LS = 'wpml_get_admin_ls_links';
	const ACTION_DUPLICATE      = 'make_duplicates';

	private $meta_boxes_post_edit_html;
	private $translation_management;

	private $admin_language_switcher;

	private $post_translations;

	private $lang_pair_permissions;

	public function __construct(
		WPML_Meta_Boxes_Post_Edit_HTML $meta_boxes_post_edit_html,
		TranslationManagement $iclTranslationManagement,
		WPML_Admin_Language_Switcher $admin_language_switcher,
		?WPML_Post_Translation $post_translations = null,
		?LangPairPermissions $lang_pair_permissions = null
	) {
		$this->translation_management = $iclTranslationManagement;
		$this->meta_boxes_post_edit_html = $meta_boxes_post_edit_html;
		$this->admin_language_switcher = $admin_language_switcher;
		$this->post_translations = $post_translations;
		$this->lang_pair_permissions = $lang_pair_permissions ?: new LangPairPermissions();
	}

	public function add_hooks() {
		\WPML\Request\Adapter\Ajax::register( self::ACTION_GET_META_BOXES, \WPML\Request\Policy\Policy::capability( 'edit_posts', \WPML\Request\Policy\Authenticity::actionNonce( self::ACTION_GET_META_BOXES, 'nonce' ) ), array( $this, 'render_meta_boxes_html' ) );
		\WPML\Request\Adapter\Ajax::register( self::ACTION_GET_ADMIN_LS, \WPML\Request\Policy\Policy::capability( 'edit_posts', \WPML\Request\Policy\Authenticity::actionNonce( self::ACTION_GET_ADMIN_LS, 'nonce' ) ), [ $this, 'get_admin_ls_links' ] );
		\WPML\Request\Adapter\Ajax::register( self::ACTION_DUPLICATE, \WPML\Request\Policy\Policy::capability( 'edit_posts', \WPML\Request\Policy\Authenticity::actionNonce( self::ACTION_DUPLICATE, 'nonce' ) ), array( $this, 'duplicate_post' ) );
		add_filter( 'wpml_post_edit_can_translate', array( $this, 'force_post_edit_when_refreshing_meta_boxes' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	public function enqueue_scripts( $hook ) {
		if (
			in_array( $hook, [ 'post.php', 'post-new.php', 'edit.php' ], true ) ||
			apply_filters( 'wpml_enable_language_meta_box', false )
		) {
			wp_enqueue_script( 'wpml-meta-box', ICL_PLUGIN_URL . '/dist/js/wpml-meta-box/wpml-meta-box.js', [], ICL_SITEPRESS_SCRIPT_VERSION, true );
		}
	}

	public function render_meta_boxes_html() {
		if ( $this->is_valid_request( self::ACTION_GET_META_BOXES ) ) {
			$post_id = (int) $_POST['post_id'];

			if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
				wp_die();

				return;
			}

			$this->meta_boxes_post_edit_html->render_languages( get_post( $post_id ) );
			wp_die();
		}
	}

	public function get_admin_ls_links() {
		if ( $this->is_valid_request( self::ACTION_GET_ADMIN_LS ) ) {
			$links = $this->admin_language_switcher->get_languages_links();
			wp_send_json_success( $links );
		}
	}

	public function force_post_edit_when_refreshing_meta_boxes( $is_edit_page ) {
		return isset( $_POST['action'] ) && self::ACTION_GET_META_BOXES === $_POST['action'] ? true : $is_edit_page;
	}

	public function duplicate_post() {
		if ( ! $this->is_valid_request( self::ACTION_DUPLICATE ) ) {
			wp_send_json_error();
			return;
		}

		$post_id = isset( $_POST['post_id'] ) && is_scalar( $_POST['post_id'] )
			? absint( wp_unslash( $_POST['post_id'] ) )
			: 0;

		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error();
			return;
		}

		$source_lang = $this->get_post_translations()->get_element_lang_code( $post_id );
		$langs_field = isset( $_POST['langs'] ) ? sanitize_text_field( wp_unslash( $_POST['langs'] ) ) : '';
		$langs       = explode( ',', $langs_field );

		$post_translations = $this->get_post_translations();
		foreach ( $langs as $lang ) {
			if ( ! $this->lang_pair_permissions->is_allowed( $source_lang, $lang, $post_id ) ) {
				wp_send_json_error();
				return;
			}

			$existing_target = (int) $post_translations->element_id_in( $post_id, $lang );
			if ( $existing_target && ! current_user_can( 'edit_post', $existing_target ) ) {
				wp_send_json_error();
				return;
			}
		}

		$mdata['iclpost'] = array( $post_id );
		foreach ( $langs as $lang ) {
			$mdata['duplicate_to'][ $lang ] = 1;
		}

		$this->translation_management->make_duplicates( $mdata );
		do_action( 'wpml_new_duplicated_terms', (array) $mdata['iclpost'], false );
		wp_send_json_success();
	}

	private function get_post_translations() {
		if ( ! $this->post_translations ) {
			global $wpml_post_translations;
			$this->post_translations = $wpml_post_translations;
		}

		return $this->post_translations;
	}

	private function is_valid_request( $action ) {
		$action = $action ? $action : self::ACTION_GET_META_BOXES;
		return isset( $_POST['nonce'] ) && wp_verify_nonce( $_POST['nonce'], $action );
	}
}
