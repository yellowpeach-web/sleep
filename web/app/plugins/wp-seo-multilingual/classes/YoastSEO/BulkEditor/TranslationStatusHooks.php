<?php

namespace WPML\WPSEO\YoastSEO\BulkEditor;

use WPML\WPSEO\YoastSEO\Meta\CopyFieldsSyncHooks;

class TranslationStatusHooks implements \IWPML_REST_Action, \IWPML_DIC_Action {

	const UPDATE_ROUTES = [
		'/yoast/v1/bulk_editor/update_search',
		'/yoast/v1/bulk_editor/update_social',
	];

	const META_CHANGE_ACTIONS = [ 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ];

	private $sitepress;

	private $changedMetaKeysByPostId = [];

	public function __construct( \SitePress $sitepress ) {
		$this->sitepress = $sitepress;
	}

	public function add_hooks() {
		add_filter( 'rest_request_before_callbacks', [ $this, 'watchYoastMetaChanges' ], 10, 3 );
		add_filter( 'rest_request_after_callbacks', [ $this, 'saveChangedOriginals' ], 10, 3 );
	}

	public function watchYoastMetaChanges( $response, $handler, \WP_REST_Request $request ) {
		if ( $this->isBulkEditorUpdate( $request ) ) {
			foreach ( self::META_CHANGE_ACTIONS as $metaChangeAction ) {
				add_action( $metaChangeAction, [ $this, 'recordChangedPost' ], 10, 3 );
			}
		}

		return $response;
	}

	public function recordChangedPost( $metaId, $postId, $metaKey ) {
		$metaKey = (string) $metaKey;
		if ( 0 === strpos( $metaKey, CopyFieldsSyncHooks::META_KEY_PREFIX ) ) {
			$this->changedMetaKeysByPostId[ (int) $postId ][ $metaKey ] = $metaKey;
		}
	}

	public function saveChangedOriginals( $response, $handler, \WP_REST_Request $request ) {
		if ( ! $this->isBulkEditorUpdate( $request ) ) {
			return $response;
		}

		foreach ( self::META_CHANGE_ACTIONS as $metaChangeAction ) {
			remove_action( $metaChangeAction, [ $this, 'recordChangedPost' ], 10 );
		}

		$changedMetaKeysByPostId       = $this->changedMetaKeysByPostId;
		$this->changedMetaKeysByPostId = [];
		foreach ( $changedMetaKeysByPostId as $postId => $changedMetaKeys ) {
			$this->saveOriginal( $postId, array_values( $changedMetaKeys ) );
		}

		return $response;
	}

	private function isBulkEditorUpdate( \WP_REST_Request $request ): bool {
		return in_array( rtrim( $request->get_route(), '/' ), self::UPDATE_ROUTES, true );
	}

	private function saveOriginal( int $postId, array $changedMetaKeys ) {
		$post = get_post( $postId );
		if ( ! $post ) {
			return;
		}

		$elementType = 'post_' . $post->post_type;
		$trid        = $this->sitepress->get_element_trid( $postId, $elementType );
		if ( ! $trid ) {
			return;
		}

		$translations     = $this->sitepress->get_element_translations( $trid, $elementType );
		$originalLanguage = $this->getLanguageIfOriginal( $postId, $translations );
		if ( ! $originalLanguage ) {
			return;
		}

		foreach ( $translations as $translation ) {
			if ( (int) $translation->element_id !== $postId ) {
				$this->sitepress->copy_custom_fields( $postId, (int) $translation->element_id, $changedMetaKeys );
			}
		}

		if ( $this->hasFieldThatFlagsTranslations( $changedMetaKeys ) ) {
			do_action( 'wpml_tm_save_post', $postId, $post, false );
			do_action( 'wpml_after_save_post', $postId, $trid, $originalLanguage, null );
		}
	}

	private function getLanguageIfOriginal( int $postId, array $translations ) {
		foreach ( $translations as $translation ) {
			if ( (int) $translation->element_id === $postId && $translation->original ) {
				return (string) $translation->language_code;
			}
		}

		return null;
	}

	private function hasFieldThatFlagsTranslations( array $metaKeys ): bool {
		$translatedFields = array_merge(
			(array) $this->sitepress->get_custom_fields_translation_settings( WPML_TRANSLATE_CUSTOM_FIELD ),
			(array) $this->sitepress->get_custom_fields_translation_settings( WPML_COPY_ONCE_CUSTOM_FIELD )
		);

		return (bool) array_intersect( $metaKeys, $translatedFields );
	}
}
