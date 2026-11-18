<?php

namespace WPML\Compatibility\Pods;

class CopiedRelationshipFields implements \IWPML_Backend_Action, \IWPML_Frontend_Action {

	const META_PREFIX = '_pods_';

	const RELATIONSHIP_TYPES = [ 'file', 'avatar', 'pick' ];

	private static $isWriting = false;

	private $duplicateAttachment;

	public function __construct( $duplicateAttachment = null ) {
		$this->duplicateAttachment = is_callable( $duplicateAttachment ) ? $duplicateAttachment : null;
	}

	public function add_hooks() {
		add_action( 'wpml_after_copy_custom_field', [ $this, 'copyRelationship' ], 10, 3 );
		add_filter( 'wpml_sync_custom_fields_batch_writer_is_safe_callback', [ $this, 'isBatchWriterCallbackSafe' ], 10, 3 );
	}

	public function isBatchWriterCallbackSafe( $isSafe, $hookName, $callback ) {
		return $isSafe || ( 'wpml_after_copy_custom_field' === $hookName && [ $this, 'copyRelationship' ] === $callback );
	}

	public function copyRelationship( $postIdFrom, $postIdTo, $metaKey ) {
		if ( self::$isWriting || ! function_exists( 'pods_api' ) ) {
			return;
		}

		$postIdFrom = (int) $postIdFrom;
		$postIdTo   = (int) $postIdTo;
		$fieldName  = $this->fieldName( (string) $metaKey );
		$postType   = get_post_type( $postIdFrom );
		if ( '' === $fieldName || ! $postType || get_post_type( $postIdTo ) !== $postType ) {
			return;
		}

		$api = pods_api();
		$pod = $api->load_pod( [ 'name' => $postType ], false );
		if ( ! is_object( $pod ) || 'post_type' !== $pod['type'] ) {
			return;
		}

		$field = $pod->get_field( $fieldName );
		if ( ! is_object( $field ) || ! $this->isCopiedRelationship( $field ) ) {
			return;
		}

		$language = apply_filters(
			'wpml_element_language_code',
			null,
			[
				'element_id'   => $postIdTo,
				'element_type' => 'post_' . $postType,
			]
		);
		if ( ! $language ) {
			return;
		}

		self::$isWriting = true;
		try {
			$targetIds  = $this->toLanguage( $this->relatedIds( $api, $pod, $field, $postIdFrom ), $field, (string) $language, $postIdFrom );
			$currentIds = array_map( 'intval', (array) $api->lookup_related_items( $field['id'], $pod['id'], $postIdTo, $field, $pod ) );
			$removedIds = array_values( array_diff( $currentIds, $targetIds ) );

			if ( $removedIds ) {
				$api->delete_relationships( $postIdTo, $removedIds, $pod, $field );
			}
			if ( $targetIds ) {
				$api->save_relationships( $postIdTo, $targetIds, $pod, $field );
			}
		} finally {
			self::$isWriting = false;
		}
	}

	private function fieldName( $metaKey ) {
		return 0 === strpos( $metaKey, self::META_PREFIX ) ? (string) substr( $metaKey, strlen( self::META_PREFIX ) ) : $metaKey;
	}

	private function isCopiedRelationship( $field ) {
		return in_array( $field['type'], self::RELATIONSHIP_TYPES, true )
			&& $field->is_relationship()
			&& false === $field->is_simple_relationship();
	}

	private function relatedIds( $api, $pod, $field, $postId ) {
		$ids = (array) $api->lookup_related_items( $field['id'], $pod['id'], $postId, $field, $pod );
		if ( ! $ids ) {
			$ids = (array) $api->lookup_related_items(
				[
					'field_id'   => $field['id'],
					'pod_id'     => $pod['id'],
					'ids'        => $postId,
					'field'      => $field,
					'pod'        => $pod,
					'force_meta' => true,
				]
			);
		}

		return array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
	}

	private function toLanguage( array $ids, $field, $language, $sourcePostId ) {
		$elementType = $this->elementType( $field );
		if ( null === $elementType ) {
			return $ids;
		}

		$mapped = [];
		foreach ( $ids as $id ) {
			$translatedId = (int) apply_filters( 'wpml_object_id', $id, $elementType, false, $language );
			if ( ! $translatedId && 'attachment' === $elementType ) {
				$translatedId = (int) $this->duplicateAttachment( $id, $language, $sourcePostId );
			}
			$mapped[] = $translatedId ?: $id;
		}

		return array_values( array_unique( $mapped ) );
	}

	private function elementType( $field ) {
		switch ( $field->get_related_object_type() ) {
			case 'media':
				return 'attachment';
			case 'post_type':
			case 'taxonomy':
				$name = $field->get_related_object_name();

				return $name ? (string) $name : null;
			default:
				return null;
		}
	}

	private function duplicateAttachment( $attachmentId, $language, $sourcePostId ) {
		if ( $this->duplicateAttachment ) {
			return call_user_func( $this->duplicateAttachment, $attachmentId, $language, $sourcePostId );
		}

		if (
			! class_exists( \WPML_Media_Attachments_Duplication_Factory::class )
			|| ! class_exists( \WPML\Media\Option::class )
			|| ! ( \WPML\Media\Option::shouldDuplicateMedia( $sourcePostId ) || \WPML\Media\Option::shouldHandleMediaAuto() )
		) {
			return null;
		}

		$duplication = ( new \WPML_Media_Attachments_Duplication_Factory() )->create();

		return $duplication
			? $duplication->create_duplicate_attachment( $attachmentId, wp_get_post_parent_id( $attachmentId ), $language )
			: null;
	}
}
