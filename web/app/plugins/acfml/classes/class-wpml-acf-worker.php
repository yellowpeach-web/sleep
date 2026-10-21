<?php

use ACFML\FilteredAcfFieldReferenceTrait;
use WPML\API\Sanitize;
use WPML\FP\Obj;

class WPML_ACF_Worker implements \IWPML_Backend_Action, \IWPML_Frontend_Action, \IWPML_DIC_Action {

	use FilteredAcfFieldReferenceTrait;

	const META_TYPE_POST = 'post';
	const META_TYPE_TERM = 'term';

	const METADATA_CONTEXT_POST_FIELD = 'custom_field';
	const METADATA_CONTEXT_TERM_FIELD = 'term_field';

	const SUSPEND_COPY_TIME_CONVERSION_FOR_FIELD_TYPES = [ 'link', 'url' ];

	const PRIORITY_CONVERT_COPIED_FIELD = 10;

	private $fieldResolver;

	private $referenceRepository;

	private $sourceMetadataSnapshots = [];

	private $referenceMetadataCopyDecisions = [];

	private $registeredFields = [];

	public function __construct( \ACFML\Field\Resolver $fieldResolver, \ACFML\Field\ReferenceRepository $referenceRepository ) {
		$this->fieldResolver       = $fieldResolver;
		$this->referenceRepository = $referenceRepository;
	}

	public function add_hooks() {
		add_filter( 'wpml_duplicate_generic_string', [ $this, 'translateMetaValue' ], 10, 3 );
		add_filter( 'wpml_sync_parent_for_post_type', [ $this, 'sync_parent_for_post_type' ], 10, 2 );
		add_action( 'wpml_after_copy_custom_field', [ $this, 'after_copy_custom_field' ], self::PRIORITY_CONVERT_COPIED_FIELD, 4 );
		add_action( 'wpml_after_copy_term_field', [ $this, 'after_copy_term_field' ], 10, 3 );
		add_filter( 'wpml_apply_translated_term_meta', [ $this, 'storeReferenceForTranslatedTermMeta' ], 10, 5 );
		add_action( 'added_post_meta', [ $this, 'invalidatePostMetadataSnapshot' ], 10, 2 );
		add_action( 'updated_post_meta', [ $this, 'invalidatePostMetadataSnapshot' ], 10, 2 );
		add_action( 'deleted_post_meta', [ $this, 'invalidatePostMetadataSnapshot' ], 10, 2 );
		add_action( 'wpml_after_batch_copy_custom_fields', [ $this, 'afterBatchCopyCustomFields' ], 10, 4 );
		add_filter( 'wpml_sync_custom_fields_batch_writer_is_safe_callback', [ $this, 'isBatchWriterCallbackSafe' ], 10, 3 );
	}

	public function invalidatePostMetadataSnapshot( $unusedMetaId, $postId ) {
		$scope = $this->getSourceMetadataScope( self::META_TYPE_POST, $postId );

		unset( $this->sourceMetadataSnapshots[ $scope ], $this->referenceMetadataCopyDecisions[ $scope ] );
	}

	public function afterBatchCopyCustomFields( $unusedPostIdFrom, $postIdTo, array $unusedMetaKeys, array $details = [] ) {
		$this->invalidatePostMetadataSnapshot( 0, $postIdTo );
		$this->referenceRepository->applyBatch( self::META_TYPE_POST, $postIdTo, $details );
	}

	public function isBatchWriterCallbackSafe( $isSafe, $hookName, $callback ) {
		if ( true === $isSafe || ! is_array( $callback ) ) {
			return $isSafe;
		}

		if (
			'wpml_after_copy_custom_field' === $hookName
			&& [ $this, 'after_copy_custom_field' ] === $callback
		) {
			return true;
		}

		return in_array( $hookName, [ 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ], true )
			&& [ $this, 'invalidatePostMetadataSnapshot' ] === $callback;
	}

	public function after_copy_custom_field( $post_id_from, $post_id_to, $meta_key, $values_after = null ) {
		if ( $this->isConfirmedAcfReferenceMetadataCopy( $post_id_from, $meta_key, $values_after ) ) {
			return;
		}

		$this->afterCopyObjectField( $post_id_from, $post_id_to, $meta_key, self::META_TYPE_POST, get_post_type( $post_id_to ), $values_after );
	}

	private function isConfirmedAcfReferenceMetadataCopy( $postIdFrom, $metaKey, $valuesAfter ) {
		if (
			! is_string( $metaKey )
			|| strlen( $metaKey ) < 2
			|| '_' !== $metaKey[0]
			|| ! is_array( $valuesAfter )
			|| 1 !== count( $valuesAfter )
		) {
			return false;
		}

		$destinationValues = array_values( $valuesAfter );
		$rawReference      = $destinationValues[0];
		if ( ! is_string( $rawReference ) || '' === $rawReference || 0 !== strpos( $rawReference, 'field_' ) ) {
			return false;
		}

		$scope       = $this->getSourceMetadataScope( self::META_TYPE_POST, $postIdFrom );
		$decisionKey = strlen( $metaKey ) . ':' . $metaKey . $rawReference;
		if ( isset( $this->referenceMetadataCopyDecisions[ $scope ] )
			&& array_key_exists( $decisionKey, $this->referenceMetadataCopyDecisions[ $scope ] )
		) {
			return $this->referenceMetadataCopyDecisions[ $scope ][ $decisionKey ];
		}

		$sourceMetadata = $this->getSourceMetadataSnapshot( self::META_TYPE_POST, $postIdFrom, $scope );
		$valueMetaKey   = substr( $metaKey, 1 );
		$sourceValues   = isset( $sourceMetadata[ $metaKey ] ) && is_array( $sourceMetadata[ $metaKey ] )
			? array_values( $sourceMetadata[ $metaKey ] )
			: [];

		$isReferenceMetadata = array_key_exists( $valueMetaKey, $sourceMetadata )
			&& 1 === count( $sourceValues )
			&& $rawReference === $sourceValues[0]
			&& ! array_key_exists( '_' . $metaKey, $sourceMetadata );

		if ( $isReferenceMetadata ) {
			$field               = $this->getRegisteredAcfField( $rawReference );
			$isReferenceMetadata = false !== $field && $this->fieldNameMatchesMetaKey( $field, $valueMetaKey );
		}

		$this->referenceMetadataCopyDecisions[ $scope ][ $decisionKey ] = $isReferenceMetadata;

		return $isReferenceMetadata;
	}

	private function getSourceMetadataScope( $metaType, $objectId ) {
		return get_current_blog_id() . ':' . $metaType . ':' . (string) $objectId;
	}

	private function getSourceMetadataSnapshot( $metaType, $objectId, $scope ) {
		if ( ! array_key_exists( $scope, $this->sourceMetadataSnapshots ) ) {
			$metadata                                = get_metadata( $metaType, $objectId );
			$this->sourceMetadataSnapshots[ $scope ] = is_array( $metadata ) ? $metadata : [];
		}

		return $this->sourceMetadataSnapshots[ $scope ];
	}

	private function getRegisteredAcfField( $reference ) {
		if ( ! is_string( $reference ) || '' === $reference || 0 !== strpos( $reference, 'field_' ) ) {
			return false;
		}

		$cacheKey = get_current_blog_id() . ':' . $reference;
		if ( ! array_key_exists( $cacheKey, $this->registeredFields ) ) {
			$field = false;
			if ( function_exists( 'acf_get_field' ) ) {
				$field = acf_get_field( $reference );
				if ( ! $this->isExactAcfFieldReference( $field, $reference ) ) {
					$normalizedReference = $this->normalizeAcfFieldReference( $reference );
					$field               = $normalizedReference !== $reference ? acf_get_field( $normalizedReference ) : false;
					$reference           = $normalizedReference;
				}
			}

			$this->registeredFields[ $cacheKey ] = $this->isExactAcfFieldReference( $field, $reference ) ? $field : false;
		}

		return $this->registeredFields[ $cacheKey ];
	}

	private function isExactAcfFieldReference( $field, $reference ) {
		return is_array( $field ) && isset( $field['key'] ) && $reference === $field['key'];
	}

	private function fieldNameMatchesMetaKey( array $field, $metaKey ) {
		$fieldName = isset( $field['name'] ) && is_string( $field['name'] ) ? $field['name'] : '';
		if ( '' === $fieldName ) {
			return false;
		}

		if ( $fieldName === $metaKey ) {
			return true;
		}

		$suffix = '_' . $fieldName;

		return strlen( $metaKey ) > strlen( $suffix ) && substr( $metaKey, -strlen( $suffix ) ) === $suffix;
	}

	public function after_copy_term_field( $term_id_from, $term_id_to, $meta_key ) {
		$term = get_term( $term_id_to );
		if ( ! $term instanceof \WP_Term ) {
			return;
		}
		$this->afterCopyObjectField( \WPML_ACF_Term_Id::normalizeId( $term_id_from ), $term_id_to, $meta_key, self::META_TYPE_TERM, $term->taxonomy );
	}

	public function storeReferenceForTranslatedTermMeta( $handled, $termId, $metaKey, $unusedValue, $context ) {
		$sourceTermId = (int) Obj::prop( 'sourceTermId', $context );
		if ( ! $sourceTermId ) {
			return $handled;
		}

		$field     = $this->getFieldObjectWithFilteredReference( $metaKey, \WPML_ACF_Term_Id::normalizeId( $sourceTermId ), false, false ) ?: [];
		$reference = $field['key'] ?? '';

		if ( $reference ) {
			$this->referenceRepository->storeIfMissing( self::META_TYPE_TERM, $termId, '_' . $metaKey, $reference );
		}

		return $handled;
	}

	private function afterCopyObjectField( $objectFromId, $objectToId, $metaKey, $metaType, $objectType, $valuesAfter = null ) {
		if ( is_array( $valuesAfter ) ) {
			$metaValue = count( $valuesAfter ) ? maybe_unserialize( $valuesAfter[0] ) : '';
		} else {
			$metaValue = get_metadata( $metaType, $objectToId, $metaKey, true );
		}

		if ( $this->hasNothingToConvert( $metaValue ) ) {
			return;
		}

		$field = $this->getFieldObjectWithFilteredReference( $metaKey, $objectFromId, false, false );
		if ( ! $field ) {
			return;
		}

		$reference = Obj::prop( 'key', $field );
		if ( $reference ) {
			$this->referenceRepository->storeIfMissing( $metaType, $objectToId, '_' . $metaKey, $reference );
		}

		$targetLang = $this->getTargetLang( $objectToId, $objectType );
		if ( ! $targetLang ) {
			return;
		}

		$metaValueConverted = $this->convertMetaValue( $metaValue, $metaKey, Obj::prop( 'type', $field ), $metaType, $objectFromId, $objectToId, $targetLang );

		if ( $metaValue !== $metaValueConverted ) {
			update_metadata( $metaType, $objectToId, $metaKey, $metaValueConverted, $metaValue );
		}

		if ( self::META_TYPE_POST === $metaType && $this->savesTermsOfTheObject( $field ) ) {
			$this->connectTermsInTargetLanguage( $objectToId, $field['taxonomy'], $metaValueConverted, $targetLang );
		}
	}

	private function savesTermsOfTheObject( array $field ): bool {
		return 'taxonomy' === Obj::prop( 'type', $field )
			&& ! empty( $field['save_terms'] )
			&& taxonomy_exists( (string) Obj::prop( 'taxonomy', $field ) );
	}

	private function connectTermsInTargetLanguage( int $postId, string $taxonomy, $termIds, string $targetLang ) {
		$isInTargetLanguage = fn( int $termId ) => $termId === (int) apply_filters( 'wpml_object_id', $termId, $taxonomy, false, $targetLang );

		$copiedTermIds           = array_map( 'intval', (array) maybe_unserialize( $termIds ) );
		$termIdsInTargetLanguage = array_values( array_filter( $copiedTermIds, $isInTargetLanguage ) );

		if ( $termIdsInTargetLanguage ) {
			wp_set_object_terms( $postId, $termIdsInTargetLanguage, $taxonomy, false );
		}
	}


	private function hasNothingToConvert( $metaValue ) {
		return '' === $metaValue || null === $metaValue || '0' === $metaValue;
	}

	public function convertMetaValue( $metaValue, $metaKey, $fieldType, $metaType, $objectFromId, $objectToId, $targetLang ) {
		$metaData = $this->prepareMetaData( $metaValue, $metaKey, $fieldType, $metaType, $objectFromId, $objectToId );
		return $this->translateMetaValue( $metaValue, $targetLang, $metaData );
	}

	private function prepareMetaData( $metaValue, $metaKey, $fieldType, $metaType, $objectFromId, $objectToId ) {
		$isSerialized = is_serialized( $metaValue );
		$idKey        = sprintf( '%s_id', $metaType );
		$masterIdKey  = sprintf( 'master_%s_id', $metaType );
		return [
			'context'       => self::META_TYPE_TERM === $metaType ? self::METADATA_CONTEXT_TERM_FIELD : self::METADATA_CONTEXT_POST_FIELD,
			'attribute'     => 'value',
			'key'           => $metaKey,
			'type'          => $fieldType,
			'is_serialized' => $isSerialized,
			$idKey          => $objectToId,
			$masterIdKey    => $objectFromId,
		];
	}

	public function translateMetaValue( $metaValue, $targetLang, $metaData ) {
		$processedData = new WPML_ACF_Processed_Data( $metaValue, $targetLang, $metaData );
		return $this->resolveMetaValue( $processedData );
	}

	private function resolveMetaValue( WPML_ACF_Processed_Data $processedData ) {
		$field = $this->fieldResolver->run( $processedData );

		if ( in_array( $field->field_type(), self::SUSPEND_COPY_TIME_CONVERSION_FOR_FIELD_TYPES, true ) ) {
			return $processedData->meta_value;
		}

		return $field->convert_ids();
	}

	public function sync_parent_for_post_type( $sync, $post_type ) {
		if ( 'acf-field' === $post_type || 'acf-field-group' === $post_type ) {
			$sync = false;
		}

		return $sync;
	}

	private function getTargetLang( $target_object_id, $target_object_type ) {
		return apply_filters( 'wpml_element_language_code', null, [
			'element_id'   => $target_object_id,
			'element_type' => $target_object_type,
		] );
	}

}
