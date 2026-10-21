<?php

namespace WPML\Compatibility\Divi\V5;

class Editor implements \IWPML_Backend_Action, \IWPML_Frontend_Action {

	const SYNC_ROUTE = '/divi/v1/sync-to-server';

	private $syncedPostId = 0;

	public function add_hooks() {
		add_filter( 'rest_pre_dispatch', [ $this, 'captureSyncedPost' ], 10, 3 );
		add_filter( 'wpml_pb_is_editing_translation_with_native_editor', [ $this, 'isEditingWithVisualBuilder' ], 10, 2 );
	}

	public function captureSyncedPost( $result, $server, $request ) {
		if (
			self::SYNC_ROUTE === $request->get_route()
			&& in_array( $request->get_method(), [ 'POST', 'PUT', 'PATCH' ], true )
		) {
			$this->syncedPostId = (int) $request->get_param( 'post_id' );
		}

		return $result;
	}

	public function isEditingWithVisualBuilder( $isNativeEditor, $translatedPostId ) {
		return $isNativeEditor
			|| ( $this->syncedPostId && $this->syncedPostId === (int) $translatedPostId );
	}
}
