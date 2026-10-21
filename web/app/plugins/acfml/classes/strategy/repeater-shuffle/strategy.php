<?php

namespace ACFML\Repeater\Shuffle;

use WPML\FP\Relation;

abstract class Strategy {
	protected $id_prefix = '';

	protected $trid = null;

	protected $element_translations = [];

	abstract public function getEntityType();

	abstract public function isValidId( $id );

	abstract protected function getElement( $id );

	public function getTrid( $elementId ) {
		if ( null === $this->trid ) {
			$this->trid = false;
			$element    = $this->getElement( $elementId );
			if ( isset( $element->id, $element->type ) ) {
				$type       = apply_filters( 'wpml_element_type', $element->type );
				$this->trid = apply_filters( 'wpml_element_trid', $this->trid, $element->id, $type );
			}
		}
		return $this->trid;
	}

	abstract public function getAllMeta( $id );

	abstract public function readOneValue( $entry );

	abstract public function getOneMeta( $id, $key, $single );

	abstract public function deleteOneMeta( $id, $key );

	abstract public function updateOneMeta( $id, $key, $val );

	abstract protected function get_element_type( $id );

	protected function getNumericId( $id ) {
		if ( ! is_numeric( $id ) ) {
			$id = substr( $id, strlen( $this->id_prefix ) );
		}
		return (int) $id;
	}

	public function hasTranslations( $id ) {
		return count( $this->getTranslations( $id ) ) > 0;
	}

	public function getTranslations( $id ) {
		if ( ! isset( $this->element_translations[ $id ] ) ) {
			$element_type                      = $this->get_element_type( $id );
			$trid                              = apply_filters( 'wpml_element_trid', false, $this->getNumericId( $id ), $element_type );
			$this->element_translations[ $id ] = apply_filters( 'wpml_get_element_translations', [], $trid, $element_type );

			foreach ( $this->element_translations[ $id ] as $language_code => $element ) {
				if ( (int) $element->element_id === $this->getNumericId( $id ) ) {
					unset( $this->element_translations[ $id ][ $language_code ] );
				}
			}
		}

		return $this->element_translations[ $id ];
	}

	public function editedNatively( $id ) {
		return false;
	}

	public function isOriginal( $id ) {
		$translations = $this->getTranslations( $id );
		return ! wpml_collect( array_values( $translations ) )
			->first( Relation::propEq( 'original', '1' ) );
	}
}
