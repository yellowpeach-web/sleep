<?php

use WPML\FP\Obj;

class WPML_ACF_Field_Groups implements \IWPML_Backend_Action, \IWPML_Frontend_Action, \IWPML_DIC_Action {
	private $sitepress;
	const POST_TYPE = 'acf-field-group';

	private $nativeEditorEnabled = [];

	public function __construct( SitePress $sitepress ) {
		$this->sitepress = $sitepress;
	}

	public function add_hooks() {
		if ( is_admin()
			&& apply_filters( 'wpml_sub_setting', false, 'custom_posts_sync_option', self::POST_TYPE )
		) {
			add_filter( 'wpml_tm_post_edit_tm_editor_selector_display', [ $this, 'disable_tm_editor_selector_for_field_group' ] );
			add_action( 'admin_init', [ $this, 'translate_field_groups_with_wp_editor' ] );
		}
	}

	private function shouldUseNativeEditor( $postId = null ) {
		$postId   = null === $postId ? get_the_ID() : $postId;
		$cacheKey = is_scalar( $postId ) ? (string) $postId : '';
		if ( ! array_key_exists( $cacheKey, $this->nativeEditorEnabled ) ) {
			$this->nativeEditorEnabled[ $cacheKey ] = (bool) apply_filters( 'acfml_use_native_editor_for_field_groups', true, $postId );
		}

		return $this->nativeEditorEnabled[ $cacheKey ];
	}

	public function disable_tm_editor_selector_for_field_group( $display ) {
		$postData = $this->getPostData();
		$postId   = 'wpml_get_meta_boxes_html' === Obj::prop( 'action', $postData )
			? (int) Obj::prop( 'post_id', $postData )
			: null;

		if ( ! $this->shouldUseNativeEditor( $postId ) ) {
			return $display;
		}

		$postType = null === $postId ? get_post_type() : get_post_type( $postId );

		return self::POST_TYPE === $postType ? false : $display;
	}

	protected function getPostData() {
		return filter_input_array( INPUT_POST ) ?: [];
	}

	public function translate_field_groups_with_wp_editor() {
		if ( $this->shouldUseNativeEditor() ) {
			$tm_settings = apply_filters( 'wpml_setting', [], 'translation-management' );
			if ( WPML_TM_Post_Edit_TM_Editor_Mode::EDITOR_NATIVE !== Obj::pathOr( null, [ WPML_TM_Post_Edit_TM_Editor_Mode::TM_KEY_FOR_POST_TYPE_EDITOR, self::POST_TYPE ], $tm_settings ) ) {
				$tm_settings[ WPML_TM_Post_Edit_TM_Editor_Mode::TM_KEY_FOR_POST_TYPE_USE_NATIVE ][ self::POST_TYPE ] = true;
				$tm_settings[ WPML_TM_Post_Edit_TM_Editor_Mode::TM_KEY_FOR_POST_TYPE_EDITOR ][ self::POST_TYPE ]     = WPML_TM_Post_Edit_TM_Editor_Mode::EDITOR_NATIVE;
				$this->sitepress->set_setting( 'translation-management', $tm_settings, true );
				WPML_TM_Post_Edit_TM_Editor_Mode::delete_all_posts_option( self::POST_TYPE );
			}
		}
	}
}
