<?php

namespace ACFML;

use ACFML\Repeater\Shuffle\Strategy;

class FieldState implements \IWPML_Backend_Action {
	
	private $metaDataBeforeUpdate;
	
	private $shuffled;
	
	const PRIORITY_BEFORE_CF_UPDATED = 5;
	
	public function __construct( Strategy $shuffled ) {
		$this->shuffled = $shuffled;
	}
	
	public function add_hooks() {
		add_action( 'acf/save_post', [ $this, 'storeStateBefore' ], self::PRIORITY_BEFORE_CF_UPDATED );
	}
	
	public function storeStateBefore( $id ) {
		if ( $this->shuffled->isValidId( $id ) && ! $this->metaDataBeforeUpdate ) {
			$this->metaDataBeforeUpdate = $this->getCurrentMetadata( $id );
		}
	}
	
	public function getStateBefore() {
		return $this->metaDataBeforeUpdate;
	}
	
	public function getCurrentMetadata( $id ) {
		try {
			$metaData = (array) $this->shuffled->getAllMeta( $id );
		} catch ( \Throwable $e ) {
			$metaData = [];
		}

		foreach ( $metaData as $key => $maybeArray ) {
			$acf_field = get_field_object( $key, $id );
			if ( ! $acf_field ) {
				unset( $metaData[ $key ] );
			} elseif ( is_array( $maybeArray ) ) {
				$metaData[ $key ] = end( $maybeArray );
			}
		}
		return $metaData;
	}
}