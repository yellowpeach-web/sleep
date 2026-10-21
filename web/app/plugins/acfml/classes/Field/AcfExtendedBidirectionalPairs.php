<?php

namespace ACFML\Field;

class AcfExtendedBidirectionalPairs {

	use IdsListingQueryTrait;
	use IdsInLanguageTrait;

	const SETTING         = 'acfe_bidirectional';
	const SETTING_ENABLED = 'acfe_bidirectional_enabled';
	const SETTING_RELATED = 'acfe_bidirectional_related';

	const META_TYPE_USER = 'user';

	const LINKED_OBJECT_TYPE_BY_FIELD_TYPE = [
		'relationship' => ReferenceRepository::META_TYPE_POST,
		'post_object'  => ReferenceRepository::META_TYPE_POST,
		'taxonomy'     => ReferenceRepository::META_TYPE_TERM,
		'user'         => self::META_TYPE_USER,
	];

	private $wpdb;

	private $isReplaying = false;

	private $previousPairing;

	private $replayedValues = [];

	public function __construct( \wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function replayUpdate( array $field, $objectId, $objectType ) {
		$linkedObjectType = self::LINKED_OBJECT_TYPE_BY_FIELD_TYPE[ $field['type'] ?? '' ] ?? null;
		if ( $this->isReplaying || ! $linkedObjectType || ! self::isBidirectional( $field ) ) {
			return;
		}

		$objectId    = (int) $objectId;
		$acfObjectId = ReferenceRepository::META_TYPE_TERM === $objectType ? 'term_' . $objectId : $objectId;
		$storedValue = get_metadata( $objectType, $objectId, $field['name'], true );

		$replayKey = $objectType . ':' . $objectId . ':' . $field['name'];
		if ( array_key_exists( $replayKey, $this->replayedValues ) && $this->replayedValues[ $replayKey ] === $storedValue ) {
			return;
		}
		$this->replayedValues[ $replayKey ] = $storedValue;

		$languageCode  = self::META_TYPE_USER === $linkedObjectType ? null : $this->getLanguageCode( $objectId, $objectType );
		$replayedValue = $this->withoutIdsInOtherLanguages( $storedValue, $linkedObjectType, $languageCode );
		$pairedIds     = $this->withoutIdsInOtherLanguages( $this->getIdsPairedWith( $objectId, $field, $linkedObjectType ), $linkedObjectType, $languageCode );

		$this->isReplaying     = true;
		$this->previousPairing = [ (string) $acfObjectId, $field['name'], $pairedIds ];
		add_filter( 'acf/pre_load_metadata', [ $this, 'loadPreviousPairing' ], 10, 4 );

		try {
			apply_filters( 'acf/update_value/type=' . $field['type'], $replayedValue, $acfObjectId, $field, $replayedValue );
		} finally {
			remove_filter( 'acf/pre_load_metadata', [ $this, 'loadPreviousPairing' ], 10 );
			$this->previousPairing = null;
			$this->isReplaying     = false;
		}
	}

	public function loadPreviousPairing( $value, $acfObjectId, $name, $hidden ) {
		if ( null !== $value || $hidden || ! $this->previousPairing ) {
			return $value;
		}

		list( $replayedObjectId, $replayedName, $pairedIds ) = $this->previousPairing;

		return $replayedObjectId === (string) $acfObjectId && $replayedName === $name ? $pairedIds : $value;
	}

	private function withoutIdsInOtherLanguages( $value, string $linkedObjectType, ?string $languageCode ) {
		if ( ! $languageCode || ! $value ) {
			return $value;
		}

		$isInOtherLanguage = function ( $id ) use ( $linkedObjectType, $languageCode ) {
			$idLanguageCode = is_numeric( $id ) ? $this->getLanguageCode( (int) $id, $linkedObjectType ) : null;

			return $idLanguageCode && $languageCode !== $idLanguageCode;
		};

		if ( ! is_array( $value ) ) {
			return $isInOtherLanguage( $value ) ? '' : $value;
		}

		return array_values( array_filter( $value, fn( $id ) => ! $isInOtherLanguage( $id ) ) );
	}

	private static function isBidirectional( array $field ) {
		return ! empty( $field[ self::SETTING ][ self::SETTING_ENABLED ] )
			&& ! empty( $field[ self::SETTING ][ self::SETTING_RELATED ] );
	}

	private function getIdsPairedWith( $objectId, array $field, $linkedObjectType ) {
		$pairedIds = [];

		foreach ( (array) $field[ self::SETTING ][ self::SETTING_RELATED ] as $relatedFieldKey ) {
			$relatedField = acf_get_field( $relatedFieldKey );
			if ( is_array( $relatedField ) && ! empty( $relatedField['name'] ) ) {
				$pairedIds = array_merge( $pairedIds, $this->getIdsListing( $objectId, $relatedField['name'], $linkedObjectType ) );
			}
		}

		return array_values( array_unique( $pairedIds ) );
	}
}
