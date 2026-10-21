<?php

namespace ACFML\Repeater\Shuffle;

class Term extends Strategy {

	use MetaValueTrait;

	protected $id_prefix = 'term_';

	protected $taxonomy;

	public function __construct( $taxonomy ) {
		$this->taxonomy = $taxonomy;
	}

	public function getEntityType() {
		return 'taxonomy';
	}

	public function isValidId( $id ) {
		return strpos( $id, $this->id_prefix ) === 0 && $this->getNumericId( $id ) > 0;
	}

	protected function getElement( $id ) {
		if ( $this->isValidId( $id ) ) {
			$id   = $this->getNumericId( $id );
			$term = get_term( $id );
			if ( isset( $term->taxonomy ) ) {
				return (object) [
					'id'   => $id,
					'type' => $term->taxonomy,
				];
			}
		}
	}

	public function getAllMeta( $id ) {
		return get_term_meta( $this->getNumericId( $id ) );
	}

	public function getOneMeta( $id, $key, $single = true ) {
		return get_term_meta( $this->getNumericId( $id ), $key, $single );
	}

	public function deleteOneMeta( $id, $key ) {
		delete_term_meta( $this->getNumericId( $id ), $key );
	}

	public function updateOneMeta( $id, $key, $val ) {
		update_term_meta( $this->getNumericId( $id ), $key, $val );
	}

	protected function get_element_type( $id = null ) {
		return apply_filters( 'wpml_element_type', $this->taxonomy );
	}
}
