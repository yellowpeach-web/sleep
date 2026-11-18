<?php

namespace WPML\Compatibility\WoodMart\Hooks;

class LabelConditionIds implements \IWPML_Frontend_Action, \IWPML_AJAX_Action {

	const ID_TYPES = [
		'products'             => 'product',
		'product_cats'         => 'product_cat',
		'product_cat_children' => 'product_cat',
	];

	const TAXONOMY_LISTS = [
		'product_tag',
		'product_brand',
		'product_shipping_class',
	];

	const ATTRIBUTE_TERMS = 'product_attr_term';

	private $passedLists = [];

	public function add_hooks() {
		add_filter( 'wpml_object_id', [ $this, 'rememberPassedList' ], 9, 2 );
		add_filter( 'wpml_object_id', [ $this, 'convertConditionIds' ], 11, 4 );
	}

	public function rememberPassedList( $elementId, $elementType = null ) {
		if ( $this->isTaxonomyList( $elementType ) ) {
			$this->passedLists[] = $elementId;
		}

		return $elementId;
	}

	public function convertConditionIds( $elementId, $elementType = null, $returnOriginal = false, $languageCode = null ) {
		if ( $this->isTaxonomyList( $elementType ) ) {
			return $this->convertTaxonomyList( $elementId, $elementType, $languageCode );
		}

		if ( self::ATTRIBUTE_TERMS === $elementType ) {
			return $this->convertEach(
				$elementId,
				function ( $id ) use ( $languageCode ) {
					$term = get_term( $id );

					return $term instanceof \WP_Term ? $this->convertTermId( $id, $term->taxonomy, $languageCode ) : $id;
				}
			);
		}

		if ( ! is_string( $elementType ) || ! array_key_exists( $elementType, self::ID_TYPES ) ) {
			return $elementId;
		}

		$idType = self::ID_TYPES[ $elementType ];

		return $this->convertEach(
			$elementId,
			function ( $id ) use ( $idType, $languageCode ) {
				return $this->convertId( $id, $idType, $languageCode );
			}
		);
	}

	private function isTaxonomyList( $elementType ) {
		return is_string( $elementType ) && in_array( $elementType, self::TAXONOMY_LISTS, true );
	}

	private function convertTaxonomyList( $elementId, $taxonomy, $languageCode ) {
		if ( ! $this->passedLists ) {
			return $elementId;
		}

		$ids = $this->idsOf( array_pop( $this->passedLists ) );

		if ( ! $ids || count( $ids ) < 2 ) {
			return $elementId;
		}

		$convertedIds = [];
		foreach ( $ids as $id ) {
			$convertedIds[] = $this->convertTermId( $id, $taxonomy, $languageCode );
		}

		return implode( ',', $convertedIds );
	}

	private function convertEach( $value, callable $convert ) {
		$ids = $this->idsOf( $value );

		if ( ! $ids ) {
			return $value;
		}

		$convertedIds = array_map( $convert, $ids );

		return 1 === count( $convertedIds ) ? $convertedIds[0] : implode( ',', $convertedIds );
	}

	private function idsOf( $value ) {
		if ( is_int( $value ) ) {
			return $value > 0 ? [ $value ] : null;
		}

		if ( ! is_string( $value ) || ! preg_match( '/^\d+(,\d+)*$/', $value ) ) {
			return null;
		}

		$ids = array_map( 'intval', explode( ',', $value ) );

		return min( $ids ) > 0 ? $ids : null;
	}

	private function convertId( $id, $idType, $languageCode ) {
		$convertedId = apply_filters( 'wpml_object_id', $id, $idType, true, $languageCode );

		return $this->positiveInt( $convertedId ) ?: $id;
	}

	private function convertTermId( $id, $taxonomy, $languageCode ) {
		$convertedId = $this->positiveInt( apply_filters( 'wpml_object_id', $id, $taxonomy, false, $languageCode ) );

		if ( $convertedId ) {
			return $convertedId;
		}

		$defaultLanguageId = $this->positiveInt( apply_filters( 'wpml_object_id', $id, $taxonomy, false, apply_filters( 'wpml_default_language', null ) ) );

		return $defaultLanguageId ?: $id;
	}

	private function positiveInt( $value ) {
		return is_numeric( $value ) && (int) $value > 0 ? (int) $value : 0;
	}
}
