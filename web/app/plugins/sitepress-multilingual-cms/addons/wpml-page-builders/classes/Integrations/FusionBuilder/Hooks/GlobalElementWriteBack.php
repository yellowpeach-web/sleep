<?php

namespace WPML\Compatibility\FusionBuilder\Hooks;

use WPML\LIB\WP\Hooks;
use function WPML\FP\spreadArgs;

class GlobalElementWriteBack implements \IWPML_Backend_Action, \IWPML_Frontend_Action {

	const GLOBAL_ELEMENT_POST_TYPE = 'fusion_element';

	const SAVED_POST_ID_REQUEST_KEYS = [ 'post_id', 'post_ID' ];

	const ALL_LANGUAGES = 'all';

	public function add_hooks() {
		Hooks::onFilter( 'wp_insert_post_empty_content', 10, 2 )
			->then( spreadArgs( [ $this, 'refuseWriteBackFromOtherLanguage' ] ) );
	}

	public function refuseWriteBackFromOtherLanguage( $shouldRefuseSave, $postData ) {
		if ( $shouldRefuseSave || ! $this->isGlobalElementWriteBack( $postData ) ) {
			return $shouldRefuseSave;
		}

		$elementLanguage   = $this->getPostLanguage( (int) $postData['ID'], self::GLOBAL_ELEMENT_POST_TYPE );
		$savedPostLanguage = $this->getSavedPostLanguage( (int) $postData['ID'] );

		return $elementLanguage && $savedPostLanguage && $elementLanguage !== $savedPostLanguage;
	}

	private function isGlobalElementWriteBack( $postData ) {
		return doing_filter( 'content_save_pre' )
			&& ! empty( $postData['ID'] )
			&& self::GLOBAL_ELEMENT_POST_TYPE === get_post_type( (int) $postData['ID'] );
	}

	private function getSavedPostLanguage( $elementId ) {
		$savedPostId = $this->getSavedPostIdFromRequest();

		if ( $savedPostId && $savedPostId !== $elementId ) {
			$savedPostType = get_post_type( $savedPostId );

			return $savedPostType ? $this->getPostLanguage( $savedPostId, $savedPostType ) : null;
		}

		$currentLanguage = apply_filters( 'wpml_current_language', null );

		return self::ALL_LANGUAGES === $currentLanguage ? null : $currentLanguage;
	}

	private function getSavedPostIdFromRequest() {
		foreach ( self::SAVED_POST_ID_REQUEST_KEYS as $requestKey ) {
			if ( isset( $_POST[ $requestKey ] ) ) {
				return (int) $_POST[ $requestKey ];
			}
		}

		return 0;
	}

	private function getPostLanguage( $postId, $postType ) {
		return apply_filters(
			'wpml_element_language_code',
			null,
			[
				'element_id'   => $postId,
				'element_type' => $postType,
			]
		);
	}
}
