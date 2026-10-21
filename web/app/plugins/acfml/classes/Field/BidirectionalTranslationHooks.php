<?php

namespace ACFML\Field;

use ACFML\FilteredAcfFieldReferenceTrait;

class BidirectionalTranslationHooks implements \IWPML_Backend_Action, \IWPML_Frontend_Action, \IWPML_DIC_Action {

	use FilteredAcfFieldReferenceTrait;

	const PRIORITY_AFTER_COPY_ONCE = 20;

	private $bidirectionalPairs;

	private $acfExtendedPairs;

	private $isAcfExtendedActive = false;

	public function __construct( BidirectionalPairs $bidirectionalPairs, AcfExtendedBidirectionalPairs $acfExtendedPairs ) {
		$this->bidirectionalPairs = $bidirectionalPairs;
		$this->acfExtendedPairs   = $acfExtendedPairs;
	}

	public function add_hooks() {
		add_action( 'wpml_after_copy_custom_field', [ $this, 'pairCopiedPostField' ], FallbackReferenceHooks::PRIORITY_AFTER_WORKER_CONVERSION, 3 );
		add_action( 'wpml_after_copy_term_field', [ $this, 'pairCopiedTermField' ], FallbackReferenceHooks::PRIORITY_AFTER_WORKER_CONVERSION, 3 );
		add_action( 'icl_make_duplicate', [ $this, 'pairDuplicatedPostFields' ], 10, 4 );
		add_filter( 'wpml_sync_custom_fields_batch_writer_is_safe_callback', [ $this, 'isBatchWriterCallbackSafe' ], 10, 3 );

		$this->isAcfExtendedActive = class_exists( 'ACFE' );
		if ( $this->isAcfExtendedActive ) {
			add_action( 'wpml_pro_translation_completed', [ $this, 'replayTranslatedPostFields' ], self::PRIORITY_AFTER_COPY_ONCE, 1 );
		}
	}

	public function pairCopiedPostField( $postIdFrom, $postIdTo, $metaKey ) {
		$field = $this->getTopLevelField( $metaKey, $postIdFrom );
		if ( $field ) {
			$this->pairField( $field, $postIdTo, ReferenceRepository::META_TYPE_POST );
		}
	}

	public function pairDuplicatedPostFields( $masterPostId, $unusedLanguageCode, $unusedPostArray, $duplicatePostId ) {
		clean_post_cache( $duplicatePostId );

		foreach ( array_keys( (array) get_post_meta( $masterPostId ) ) as $metaKey ) {
			$this->pairCopiedPostField( $masterPostId, $duplicatePostId, (string) $metaKey );
		}
	}

	public function pairCopiedTermField( $termIdFrom, $termIdTo, $metaKey ) {
		$field = $this->getTopLevelField( $metaKey, \WPML_ACF_Term_Id::normalizeId( $termIdFrom ) );
		if ( $field ) {
			$this->pairField( $field, $termIdTo, ReferenceRepository::META_TYPE_TERM );
		}
	}

	public function replayTranslatedPostFields( $postId ) {
		clean_post_cache( $postId );

		foreach ( array_keys( (array) get_post_meta( $postId ) ) as $metaKey ) {
			$field = $this->getTopLevelField( (string) $metaKey, $postId );
			if ( $field ) {
				$this->acfExtendedPairs->replayUpdate( $field, $postId, ReferenceRepository::META_TYPE_POST );
			}
		}
	}

	public function isBatchWriterCallbackSafe( $isSafe, $hookName, $callback ) {
		return $isSafe || ( 'wpml_after_copy_custom_field' === $hookName && [ $this, 'pairCopiedPostField' ] === $callback );
	}

	private function pairField( array $field, $objectId, $objectType ) {
		$this->bidirectionalPairs->pairTranslation( $field, $objectId, $objectType );

		if ( $this->isAcfExtendedActive ) {
			$this->acfExtendedPairs->replayUpdate( $field, $objectId, $objectType );
		}
	}
}
