<?php

namespace ACFML\Post;

class JobReviewHooks implements \IWPML_Frontend_Action {

	private const PRIORITY_AFTER_ACF_REVISION_SWAP = 11;

	public function add_hooks() {
		add_filter( 'acf/validate_post_id', [ $this, 'useReviewedPostInsteadOfRevision' ], self::PRIORITY_AFTER_ACF_REVISION_SWAP );
	}

	public function useReviewedPostInsteadOfRevision( $postId ) {
		$previewId = filter_input( INPUT_GET, 'preview_id', FILTER_VALIDATE_INT );

		if (
			self::isReadRequest()
			&& is_numeric( $postId )
			&& $previewId
			&& self::isReviewPreview( $previewId )
			&& self::isSameAsQueriedObject( $previewId )
			&& self::isTheTargetOrItsRevision( (int) $postId, $previewId )
			&& self::isAuthorizedPreviewTarget( $previewId )
		) {
			return $previewId;
		}

		return $postId;
	}

	private static function isReadRequest() {
		$method = isset( $_SERVER['REQUEST_METHOD'] )
			? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) )
			: 'GET';

		return 'GET' === $method || 'HEAD' === $method;
	}

	private static function isReviewPreview( $previewId ) {
		if (
			null === filter_input( INPUT_GET, 'wpmlReviewPostType' )
			|| null === filter_input( INPUT_GET, 'preview' )
		) {
			return false;
		}

		return self::hasValidPreviewNonce( $previewId );
	}

	private static function isSameAsQueriedObject( $previewId ) {
		$queriedId = (int) get_queried_object_id();

		return $queriedId && $queriedId === $previewId;
	}

	private static function isTheTargetOrItsRevision( $postId, $previewId ) {
		if ( $postId === $previewId ) {
			return true;
		}

		$post = get_post( $postId );

		return $post && 'revision' === $post->post_type && (int) $post->post_parent === $previewId;
	}

	private static function isAuthorizedPreviewTarget( $previewId ) {
		return current_user_can( 'edit_post', $previewId ) || current_user_can( 'read_post', $previewId );
	}

	private static function hasValidPreviewNonce( $previewId ) {
		$nonce = filter_input( INPUT_GET, 'preview_nonce' );

		return $nonce && wp_verify_nonce( $nonce, 'post_preview_' . $previewId );
	}
}
