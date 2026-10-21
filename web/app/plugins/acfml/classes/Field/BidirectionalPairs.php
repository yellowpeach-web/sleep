<?php

namespace ACFML\Field;

class BidirectionalPairs {

	use IdsListingQueryTrait;
	use IdsInLanguageTrait;

	const DOING_BIDIRECTIONAL_UPDATE = 'acf_doing_bidirectional_update';

	const LINKED_OBJECT_TYPE_BY_FIELD_TYPE = [
		'relationship' => ReferenceRepository::META_TYPE_POST,
		'post_object'  => ReferenceRepository::META_TYPE_POST,
		'taxonomy'     => ReferenceRepository::META_TYPE_TERM,
	];

	private $wpdb;

	public function __construct( \wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function pairTranslation( array $field, $objectId, $objectType ) {
		$linkedObjectType = self::LINKED_OBJECT_TYPE_BY_FIELD_TYPE[ $field['type'] ?? '' ] ?? null;
		if ( ! $linkedObjectType || ! $this->isBidirectional( $field ) || acf_get_data( self::DOING_BIDIRECTIONAL_UPDATE ) ) {
			return;
		}

		$objectId     = (int) $objectId;
		$languageCode = $this->getLanguageCode( $objectId, $objectType );
		$pairedFields = $this->getPairedFields( $field, $objectType );
		if ( ! $languageCode || ! $pairedFields ) {
			return;
		}

		$linkedIds = $this->getIdsInLanguage( $this->getStoredIds( $field, $objectId, $objectType ), $linkedObjectType, $languageCode );

		acf_set_data( self::DOING_BIDIRECTIONAL_UPDATE, true );

		foreach ( $pairedFields as $pairedField ) {
			foreach ( $linkedIds as $linkedId ) {
				$this->addToPairedField( $pairedField, $linkedId, $linkedObjectType, $objectId );
			}

			$staleIds = array_diff(
				$this->getIdsListing( $objectId, $pairedField['name'], $linkedObjectType ),
				$this->getIdsListedByFieldsPairedWith( $pairedField, $objectId, $objectType )
			);
			foreach ( $this->getIdsInLanguage( $staleIds, $linkedObjectType, $languageCode ) as $staleId ) {
				$this->removeFromPairedField( $pairedField, $staleId, $linkedObjectType, $objectId );
			}
		}

		acf_set_data( self::DOING_BIDIRECTIONAL_UPDATE, false );
	}

	private function isBidirectional( array $field ) {
		return acf_get_setting( 'enable_bidirection' )
			&& ! empty( $field['bidirectional'] )
			&& ! empty( $field['bidirectional_target'] )
			&& ! empty( $field['key'] );
	}

	private function getPairedFields( array $field, $objectType ) {
		$validTypes   = acf_get_valid_bidirectional_target_types( $objectType );
		$pairedFields = [];

		foreach ( acf_get_array( $field['bidirectional_target'] ) as $pairedFieldKey ) {
			$pairedField = acf_get_field( $pairedFieldKey );
			if (
				is_array( $pairedField )
				&& ! empty( $pairedField['name'] )
				&& in_array( $pairedField['type'] ?? '', $validTypes, true )
			) {
				$pairedFields[] = $pairedField;
			}
		}

		return $pairedFields;
	}

	private function getIdsListedByFieldsPairedWith( array $pairedField, $objectId, $objectType ) {
		$listedIds = [];

		foreach ( acf_get_array( $pairedField['bidirectional_target'] ?? [] ) as $fieldKey ) {
			$field = acf_get_field( $fieldKey );
			if ( is_array( $field ) && ! empty( $field['name'] ) ) {
				$listedIds = array_merge( $listedIds, $this->getStoredIds( $field, $objectId, $objectType ) );
			}
		}

		return array_map( 'intval', $listedIds );
	}

	private function getStoredIds( array $field, $objectId, $objectType ) {
		return acf_get_array( get_metadata( $objectType, $objectId, $field['name'], true ) );
	}

	private function addToPairedField( array $pairedField, $linkedId, $linkedObjectType, $objectId ) {
		$acfLinkedId = $this->getAcfObjectId( $linkedId, $linkedObjectType );
		$listedIds   = $this->getStoredIds( $pairedField, $linkedId, $linkedObjectType );

		if ( ! self::listsId( $listedIds, $objectId ) ) {
			$listedIds[] = $objectId;
			update_field( $pairedField['key'], array_values( $listedIds ), $acfLinkedId );
		}
	}

	private function removeFromPairedField( array $pairedField, $staleId, $linkedObjectType, $objectId ) {
		$acfStaleId = $this->getAcfObjectId( $staleId, $linkedObjectType );
		$listedIds  = $this->getStoredIds( $pairedField, $staleId, $linkedObjectType );

		if ( self::listsId( $listedIds, $objectId ) ) {
			$remainingIds = array_filter(
				$listedIds,
				function ( $listedId ) use ( $objectId ) {
					return (string) $listedId !== (string) $objectId;
				}
			);
			update_field( $pairedField['key'], array_values( $remainingIds ), $acfStaleId );
		}
	}

	private static function listsId( array $listedIds, $id ) {
		return in_array( (string) $id, array_map( 'strval', $listedIds ), true );
	}

	private function getAcfObjectId( $id, $objectType ) {
		return ReferenceRepository::META_TYPE_TERM === $objectType ? 'term_' . $id : $id;
	}
}
