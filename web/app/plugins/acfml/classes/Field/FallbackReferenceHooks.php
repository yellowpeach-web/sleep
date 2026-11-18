<?php

namespace ACFML\Field;

use ACFML\FilteredAcfFieldReferenceTrait;
use ACFML\User\FallbackReferences as UserFallbackReferences;
use WPML\FP\Obj;

class FallbackReferenceHooks implements \IWPML_Backend_Action, \IWPML_Frontend_Action, \IWPML_DIC_Action {

	use FilteredAcfFieldReferenceTrait;

	const MARKER_KEY = '_acfml_fallback_referrer';

	const PRIORITY_AFTER_WORKER_CONVERSION = \WPML_ACF_Worker::PRIORITY_CONVERT_COPIED_FIELD + 1;

	private $bidirectionalPairs;

	private $userFallbackReferences;

	private $handled = [];

	public function __construct( BidirectionalPairs $bidirectionalPairs, UserFallbackReferences $userFallbackReferences ) {
		$this->bidirectionalPairs     = $bidirectionalPairs;
		$this->userFallbackReferences = $userFallbackReferences;
	}

	public function add_hooks() {
		add_action( 'wpml_after_copy_custom_field', [ $this, 'recordFallbacks' ], self::PRIORITY_AFTER_WORKER_CONVERSION, 3 );
		add_filter( 'wpml_sync_custom_fields_batch_writer_is_safe_callback', [ $this, 'isBatchWriterCallbackSafe' ], 10, 3 );
		add_action( 'wpml_translation_update', [ $this, 'onTranslationUpdate' ] );
		add_action( 'wp_after_insert_post', [ $this, 'onPostInserted' ], 10, 4 );
		add_action( 'wpml_after_save_post', [ $this, 'repointUnmarkedFallbacks' ], 10, 4 );
		add_filter( 'wpml_duplicate_custom_fields_exceptions', [ $this, 'excludeMarkersFromDuplication' ] );
	}

	public function repointUnmarkedFallbacks( $postId, $trid, $unusedLanguageCode, $sourceLanguageCode ) {
		$pairs = $this->getOriginalAndTranslationPairs( (int) $postId, (int) $trid, (string) $sourceLanguageCode );

		foreach ( $pairs as list( $sourceId, $translationId, $languageCode ) ) {
			$sourceMetadata      = (array) get_post_meta( $sourceId );
			$translationMetadata = (array) get_post_meta( $translationId );

			foreach ( $sourceMetadata as $metaKey => $rawValues ) {
				if (
					$this->holdsIdsOnBothSides( (string) $metaKey, $rawValues, $sourceMetadata, $translationMetadata )
					&& self::holdsAnIdOfTheSource( (array) $rawValues, (array) $translationMetadata[ $metaKey ] )
				) {
					$isSameValue = array_values( (array) $rawValues ) === array_values( (array) $translationMetadata[ $metaKey ] );
					$this->repointHeldIds( $sourceId, $translationId, (string) $metaKey, $languageCode, $isSameValue );
				}
			}
		}
	}

	private function getOriginalAndTranslationPairs( int $postId, int $trid, string $sourceLanguageCode ): array {
		if ( ! $postId || ! $trid ) {
			return [];
		}

		$elements   = $this->getTranslationGroup( $trid, 'post_' . get_post_type( $postId ) );
		$originalId = self::getOriginalId( $elements );
		if ( ! $originalId ) {
			return [];
		}

		$isOriginalSaved = '' === $sourceLanguageCode;

		$pairs = [];
		foreach ( $elements as $element ) {
			$translationId   = (int) $element->element_id;
			$isPairOfThePost = $isOriginalSaved ? $translationId !== $originalId : $translationId === $postId;
			if ( $isPairOfThePost ) {
				$pairs[] = [ $originalId, $translationId, (string) $element->language_code ];
			}
		}

		return $pairs;
	}

	private static function getOriginalId( array $elements ): int {
		foreach ( $elements as $element ) {
			if ( ! $element->source_language_code ) {
				return (int) $element->element_id;
			}
		}

		return 0;
	}

	private function holdsIdsOnBothSides( string $metaKey, $rawValues, array $sourceMetadata, array $translationMetadata ): bool {
		if ( '' === $metaKey || '_' === $metaKey[0] || ! isset( $translationMetadata[ $metaKey ] ) ) {
			return false;
		}

		$rawValues            = array_values( (array) $rawValues );
		$translationRawValues = array_values( (array) $translationMetadata[ $metaKey ] );
		$reference            = (array) ( $sourceMetadata[ '_' . $metaKey ] ?? [] );

		return 1 === count( $rawValues )
			&& 1 === count( $translationRawValues )
			&& 0 === strpos( (string) reset( $reference ), 'field_' )
			&& self::holdsOnlyIds( \WPML\Utils\Unserialize::maybe( $rawValues[0] ) )
			&& self::holdsOnlyIds( \WPML\Utils\Unserialize::maybe( $translationRawValues[0] ) );
	}

	private static function holdsAnIdOfTheSource( array $rawValues, array $translationRawValues ): bool {
		$sourceIds = array_map( 'strval', (array) \WPML\Utils\Unserialize::maybe( reset( $rawValues ) ) );
		$ids       = array_map( 'strval', (array) \WPML\Utils\Unserialize::maybe( reset( $translationRawValues ) ) );

		return (bool) array_intersect( $ids, $sourceIds );
	}

	private static function holdsOnlyIds( $value ): bool {
		$ids = (array) $value;

		return $ids && count( $ids ) === count( array_filter( $ids, 'is_numeric' ) );
	}

	private function repointHeldIds( int $sourceId, int $translationId, string $metaKey, string $languageCode, bool $isSameValue ) {
		$field = $this->getCopiedRelationalField( $metaKey, $sourceId );
		if ( ! $field ) {
			return;
		}

		$isCopyField = WPML_COPY_CUSTOM_FIELD === (int) Obj::prop( 'wpml_cf_preferences', $field );
		if ( ! $isSameValue && ! $isCopyField ) {
			return;
		}

		$value       = get_post_meta( $translationId, $metaKey, true );
		$sourceValue = get_post_meta( $sourceId, $metaKey, true );
		$newValue    = RelationalIds::translateHeldFallbacks( $value, $sourceValue, $field, $languageCode );

		if ( $newValue !== $value && false !== update_post_meta( $translationId, $metaKey, $newValue, $value ) ) {
			$repointedIds = self::getRepointedIds( $value, $newValue );
			$this->deleteMarkersOf( $field, $repointedIds, $translationId . ':' . $metaKey );
			$this->bidirectionalPairs->unpairReleasedIds( $field, $translationId, ReferenceRepository::META_TYPE_POST, $repointedIds );
			$this->bidirectionalPairs->pairTranslation( $field, $translationId, ReferenceRepository::META_TYPE_POST );
		}

		$hasUntranslatedId = false;
		foreach ( (array) $sourceValue as $id ) {
			$hasUntranslatedId = $hasUntranslatedId || ! RelationalIds::getTranslatedId( (int) $id, $field, $languageCode );
		}

		if ( $hasUntranslatedId ) {
			$this->recordFallbacks( $sourceId, $translationId, $metaKey );
		}
	}

	private static function getRepointedIds( $value, $newValue ): array {
		$ids = array_diff( array_map( 'strval', (array) $value ), array_map( 'strval', (array) $newValue ) );

		return array_values( array_unique( array_map( 'intval', $ids ) ) );
	}

	private function deleteMarkersOf( array $field, array $ids, string $marker ) {
		$metaType = RelationalIds::holdsTermIds( $field ) ? ReferenceRepository::META_TYPE_TERM : ReferenceRepository::META_TYPE_POST;

		foreach ( $ids as $id ) {
			delete_metadata( $metaType, $id, self::MARKER_KEY, $marker );
		}
	}

	public function recordFallbacks( $postIdFrom, $postIdTo, $metaKey ) {
		if ( ! is_string( $metaKey ) || '' === $metaKey || '_' === $metaKey[0] ) {
			return;
		}

		$field = $this->getCopiedRelationalField( $metaKey, $postIdFrom );
		if ( ! $field ) {
			return;
		}

		$targetLang = apply_filters(
			'wpml_element_language_code',
			null,
			[
				'element_id'   => $postIdTo,
				'element_type' => get_post_type( $postIdTo ),
			]
		);
		if ( ! $targetLang ) {
			return;
		}

		$marker = $postIdTo . ':' . $metaKey;

		foreach ( (array) get_post_meta( $postIdFrom, $metaKey, true ) as $id ) {
			$id = is_numeric( $id ) ? (int) $id : 0;
			if ( $id <= 0 ) {
				continue;
			}

			$elementType = RelationalIds::getElementType( $id, $field );
			if ( $elementType && null === apply_filters( 'wpml_object_id', $id, $elementType, false, $targetLang ) ) {
				RelationalIds::addFallbackMarker( $field, $id, $marker );
			}
		}
	}

	public function isBatchWriterCallbackSafe( $isSafe, $hookName, $callback ) {
		return $isSafe || ( 'wpml_after_copy_custom_field' === $hookName && [ $this, 'recordFallbacks' ] === $callback );
	}

	public function excludeMarkersFromDuplication( $exceptions ) {
		return is_array( $exceptions ) ? array_merge( $exceptions, [ self::MARKER_KEY ] ) : $exceptions;
	}

	public function onTranslationUpdate( $args ) {
		$type = Obj::prop( 'type', $args );

		if ( 'insert' === $type || $this->isExistingElementNewlyLinked( $type ) ) {
			$this->repointFallbacksTo( (int) Obj::prop( 'element_id', $args ), (string) Obj::prop( 'element_type', $args ) );
		}
	}

	private function isExistingElementNewlyLinked( $translationUpdateType ): bool {
		return 'update' === $translationUpdateType && ! doing_action( 'save_post' );
	}

	public function onPostInserted( $postId, $post, $unusedUpdate, $postBefore ) {
		if ( ! $this->isFirstSaveOfNativeEditorPost( $postBefore ) ) {
			return;
		}

		$elementType = 'post_' . $post->post_type;

		$this->repointFallbacksTo( (int) $postId, $elementType );
		$this->recordFallbacksOfNewTranslation( (int) $postId, $elementType );
	}

	private function isFirstSaveOfNativeEditorPost( $postBefore ): bool {
		return $postBefore instanceof \WP_Post && 'auto-draft' === $postBefore->post_status;
	}

	private function recordFallbacksOfNewTranslation( $postId, $elementType ) {
		$translation = $this->getLanguageDetails( $postId, $elementType );
		if ( ! $translation || ! $translation->source_language_code ) {
			return;
		}

		$sourceId = self::getElementIdInLanguage(
			$this->getTranslationGroup( (int) $translation->trid, $elementType ),
			(string) $translation->source_language_code
		);
		if ( ! $sourceId ) {
			return;
		}

		foreach ( array_keys( (array) get_post_meta( $sourceId ) ) as $metaKey ) {
			$this->recordFallbacks( $sourceId, $postId, (string) $metaKey );
		}
	}

	private function repointFallbacksTo( $elementId, $elementType ) {
		$handledKey = $elementType . ':' . $elementId;
		if ( ! $elementId || isset( $this->handled[ $handledKey ] ) ) {
			return;
		}

		$isTerm = 0 === strpos( $elementType, 'tax_' );
		if ( ! $isTerm && in_array( get_post_status( $elementId ), [ false, 'auto-draft', 'trash' ], true ) ) {
			return;
		}

		$element = $this->getLanguageDetails( $elementId, $elementType );
		if ( ! $element || ! $element->source_language_code ) {
			return;
		}

		$this->handled[ $handledKey ] = true;

		$group        = $this->getTranslationGroup( (int) $element->trid, $elementType );
		$metaType     = $isTerm ? ReferenceRepository::META_TYPE_TERM : ReferenceRepository::META_TYPE_POST;
		$translatedId = $isTerm ? self::getTermIdOf( $group, (int) $elementId ) : $elementId;

		foreach ( self::getIdsInOtherLanguages( $group, (string) $element->language_code, $isTerm ) as $referencedId ) {
			foreach ( (array) get_metadata( $metaType, $referencedId, self::MARKER_KEY ) as $marker ) {
				if ( $this->repointMarkedReferrer( (string) $marker, (int) $referencedId, $translatedId, $element->language_code ) ) {
					delete_metadata( $metaType, $referencedId, self::MARKER_KEY, $marker );
				}
			}
		}
	}

	private function repointMarkedReferrer( string $marker, int $referencedId, int $translatedId, string $languageCode ) {
		return UserFallbackReferences::isUserMarker( $marker )
			? $this->userFallbackReferences->repoint( $marker, $referencedId, $translatedId, $languageCode )
			: $this->repointReferrer( $marker, $referencedId, $translatedId, $languageCode );
	}

	private function repointReferrer( $marker, $referencedId, $translatedId, $languageCode ) {
		list( $referrerId, $metaKey ) = array_pad( explode( ':', $marker, 2 ), 2, '' );

		$referrerType = 'post_' . get_post_type( (int) $referrerId );
		$referrer     = $this->getLanguageDetails( (int) $referrerId, $referrerType );
		if ( ! $referrer ) {
			return true;
		}

		if ( $languageCode !== $referrer->language_code ) {
			return false;
		}

		$sourceId = self::getElementIdInLanguage(
			$this->getTranslationGroup( (int) $referrer->trid, $referrerType ),
			(string) $referrer->source_language_code
		);
		$field    = $sourceId ? $this->getCopiedRelationalField( $metaKey, $sourceId ) : null;
		if ( ! $field ) {
			return true;
		}

		$value    = get_post_meta( (int) $referrerId, $metaKey, true );
		$newValue = RelationalIds::replaceFallback( $value, get_post_meta( $sourceId, $metaKey, true ), $referencedId, $translatedId );
		if ( $newValue === $value ) {
			return true;
		}

		if ( false === update_post_meta( (int) $referrerId, $metaKey, $newValue, $value ) ) {
			return false;
		}

		$this->bidirectionalPairs->unpairReleasedIds( $field, (int) $referrerId, ReferenceRepository::META_TYPE_POST, self::getRepointedIds( $value, $newValue ) );
		$this->bidirectionalPairs->pairTranslation( $field, (int) $referrerId, ReferenceRepository::META_TYPE_POST );

		return true;
	}

	private function getCopiedRelationalField( $metaKey, $postId ) {
		$field = $this->getFieldObjectWithFilteredReference( $metaKey, $postId, false, false );
		if ( ! is_array( $field ) ) {
			return null;
		}

		$isRelational = RelationalIds::isRelational( $field );
		$isCopied     = in_array( (int) Obj::prop( 'wpml_cf_preferences', $field ), [ WPML_COPY_CUSTOM_FIELD, WPML_COPY_ONCE_CUSTOM_FIELD ], true );

		return $isRelational && $isCopied ? $field : null;
	}

	private function getLanguageDetails( $elementId, $elementType ) {
		$details = apply_filters(
			'wpml_element_language_details',
			null,
			[
				'element_id'   => (int) $elementId,
				'element_type' => $elementType,
			]
		);

		return is_object( $details ) && ! empty( $details->trid ) ? $details : null;
	}

	private function getTranslationGroup( int $trid, string $elementType ): array {
		$rows = apply_filters( 'wpml_get_element_translations', [], $trid, $elementType, false, true, true );

		return array_values(
			array_filter(
				is_array( $rows ) ? $rows : [],
				function ( $row ) {
					return is_object( $row ) && ! empty( $row->element_id );
				}
			)
		);
	}

	private static function getElementIdInLanguage( array $group, string $languageCode ): int {
		foreach ( $group as $row ) {
			if ( $languageCode === $row->language_code ) {
				return (int) $row->element_id;
			}
		}

		return 0;
	}

	private static function getIdsInOtherLanguages( array $group, string $languageCode, bool $isTerm ): array {
		$ids = [];
		foreach ( $group as $row ) {
			$id = (int) ( $isTerm ? Obj::prop( 'term_id', $row ) : $row->element_id );
			if ( $id && $languageCode !== $row->language_code ) {
				$ids[] = $id;
			}
		}

		return $ids;
	}

	private static function getTermIdOf( array $group, int $termTaxonomyId ): int {
		foreach ( $group as $row ) {
			if ( $termTaxonomyId === (int) $row->element_id ) {
				return (int) Obj::prop( 'term_id', $row );
			}
		}

		return 0;
	}
}
