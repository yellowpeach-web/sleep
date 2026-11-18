<?php

namespace WPML\Compatibility\Divi\V5;

use WPML\Compatibility\Divi\Hooks\TranslationGuiLabels;

class Editor implements \IWPML_Backend_Action, \IWPML_Frontend_Action {

	const SYNC_ROUTE                = '/divi/v1/sync-to-server';
	const PAGE_MANAGER_UPDATE_ROUTE = '/divi/v1/page-manager/update';
	const SET_POST_LAYOUT_ROUTE     = '/divi/v1/outside-vb/posts/set-layout';
	const UPSERT_LAYOUT_ROUTE       = '/divi/v1/outside-vb/theme-builder/upsert-layout';

	const WRITE_METHODS = [ 'POST', 'PUT', 'PATCH' ];

	const SINGULAR_MAIN_LOOP = 'singular';

	const LAYOUT_POST_TYPES = [
		'header' => TranslationGuiLabels::POST_TYPE_LAYOUT_HEADER,
		'footer' => TranslationGuiLabels::POST_TYPE_LAYOUT_FOOTER,
	];

	private $editedPostIds = [];

	private $upsertedLayoutPostType = '';

	private $hasSwitchedForSync = false;

	public function add_hooks() {
		add_filter( 'rest_pre_dispatch', [ $this, 'captureEditedPosts' ], 10, 3 );
		add_filter( 'rest_request_before_callbacks', [ $this, 'switchToSyncedPostLanguage' ], 10, 3 );
		add_filter( 'rest_request_after_callbacks', [ $this, 'restoreLanguageAfterSync' ], 10, 3 );
		add_action( 'pre_post_update', [ $this, 'captureUpsertedLayout' ] );
		add_filter( 'wpml_pb_is_editing_translation_with_native_editor', [ $this, 'isEditingWithVisualBuilder' ], 10, 2 );
	}

	public function captureEditedPosts( $result, $server, $request ) {
		if ( ! in_array( $request->get_method(), self::WRITE_METHODS, true ) ) {
			return $result;
		}

		switch ( $request->get_route() ) {
			case self::SYNC_ROUTE:
				$layoutPostIds = $request->get_param( 'layout_post_ids' );
				$this->addEditedPostIds(
					array_merge(
						[ $request->get_param( 'post_id' ) ],
						is_array( $layoutPostIds ) ? array_values( $layoutPostIds ) : []
					)
				);
				break;

			case self::PAGE_MANAGER_UPDATE_ROUTE:
				$this->addEditedPostIds( [ $request->get_param( 'id' ) ] );
				break;

			case self::SET_POST_LAYOUT_ROUTE:
				$this->addEditedPostIds( [ $request->get_param( 'post_id' ) ] );
				break;

			case self::UPSERT_LAYOUT_ROUTE:
				$layoutType = (string) $request->get_param( 'layout_type' );

				$this->upsertedLayoutPostType = isset( self::LAYOUT_POST_TYPES[ $layoutType ] )
					? self::LAYOUT_POST_TYPES[ $layoutType ]
					: '';
				break;
		}

		return $result;
	}

	public function switchToSyncedPostLanguage( $response, $handler, $request ) {
		if (
			self::SYNC_ROUTE !== $request->get_route()
			|| ! in_array( $request->get_method(), self::WRITE_METHODS, true )
			|| ! $this->isSingularSync( $request )
		) {
			return $response;
		}

		$details  = apply_filters( 'wpml_post_language_details', null, (int) $request->get_param( 'post_id' ) );
		$language = is_array( $details ) && ! empty( $details['language_code'] ) ? $details['language_code'] : null;
		$current  = apply_filters( 'wpml_current_language', null );

		if ( $language && $language !== $current ) {
			do_action( 'wpml_switch_language', $language );
			$this->hasSwitchedForSync = true;
		}

		return $response;
	}

	public function restoreLanguageAfterSync( $response, $handler, $request ) {
		if ( $this->hasSwitchedForSync && self::SYNC_ROUTE === $request->get_route() ) {
			$this->hasSwitchedForSync = false;
			do_action( 'wpml_switch_language', null );
		}

		return $response;
	}

	public function captureUpsertedLayout( $postId ) {
		if ( $this->upsertedLayoutPostType && get_post_type( $postId ) === $this->upsertedLayoutPostType ) {
			$this->addEditedPostIds( [ $postId ] );
			$this->upsertedLayoutPostType = '';
		}
	}

	public function isEditingWithVisualBuilder( $isNativeEditor, $translatedPostId ) {
		return $isNativeEditor || in_array( (int) $translatedPostId, $this->editedPostIds, true );
	}

	private function isSingularSync( \WP_REST_Request $request ): bool {
		$mainLoopType = $request->get_param( 'mainLoopType' );

		return ! is_string( $mainLoopType ) || self::SINGULAR_MAIN_LOOP === $mainLoopType;
	}

	private function addEditedPostIds( array $postIds ) {
		$this->editedPostIds = array_values(
			array_unique( array_merge( $this->editedPostIds, array_filter( array_map( 'intval', $postIds ) ) ) )
		);
	}
}
