<?php

namespace WPML\PB\Cornerstone\Hooks;

use WPML\LIB\WP\Hooks;
use WPML\PB\Cornerstone\Utils;
use function WPML\FP\spreadArgs;

class Assignments implements \IWPML_Frontend_Action, \IWPML_Backend_Action {

	const AFTER_CORNERSTONE_TRANSLATED_ASSIGNMENT = 0;

	const CACHED_ASSIGNMENT_OPTIONS = [
		'cs_assignment_cache_layout_header',
		'cs_assignment_cache_layout_footer',
		'cs_assignment_cache_layout_single',
		'cs_assignment_cache_layout_archive',
		'cs_assignment_cache_layout_single-wc',
		'cs_assignment_cache_layout_archive-wc',
	];

	private $postTranslations;

	public function __construct( \WPML_Post_Translation $postTranslations ) {
		$this->postTranslations = $postTranslations;
	}

	public function add_hooks() {
		Hooks::onFilter( 'cs_detect_assigned_document', self::AFTER_CORNERSTONE_TRANSLATED_ASSIGNMENT )
			->then( spreadArgs( [ $this, 'useOriginalWhenNotTranslated' ] ) );

		Hooks::onAction( 'save_post', 10, 2 )
			->then( spreadArgs( [ $this, 'clearCachedAssignmentsForLayout' ] ) );

		Hooks::onAction( 'deleted_post', 10, 2 )
			->then( spreadArgs( [ $this, 'clearCachedAssignmentsForLayout' ] ) );
	}

	public function useOriginalWhenNotTranslated( $typeAndDocumentId ) {
		if ( ! is_array( $typeAndDocumentId ) || empty( $typeAndDocumentId[1] ) || ! is_numeric( $typeAndDocumentId[1] ) ) {
			return $typeAndDocumentId;
		}

		$documentId = (int) $typeAndDocumentId[1];
		$postType   = get_post_type( $documentId );

		if ( ! $postType || ! Utils::isLayoutPostType( $postType ) ) {
			return $typeAndDocumentId;
		}

		$currentLanguage = apply_filters( 'wpml_current_language', null );
		$translatedId    = (int) apply_filters( 'wpml_object_id', $documentId, $postType, false, $currentLanguage );

		if ( $translatedId ) {
			$typeAndDocumentId[1] = $translatedId;

			return $typeAndDocumentId;
		}

		$originalId = (int) $this->postTranslations->get_original_element( $documentId, true );
		if ( $originalId ) {
			$typeAndDocumentId[1] = $originalId;
		}

		return $typeAndDocumentId;
	}

	public function clearCachedAssignmentsForLayout( $postId, $post = null ) {
		$postType = $post instanceof \WP_Post ? $post->post_type : get_post_type( $postId );

		if ( ! $postType || ! Utils::isLayoutPostType( $postType ) ) {
			return;
		}

		foreach ( self::CACHED_ASSIGNMENT_OPTIONS as $option ) {
			delete_option( $option );
		}
	}
}
