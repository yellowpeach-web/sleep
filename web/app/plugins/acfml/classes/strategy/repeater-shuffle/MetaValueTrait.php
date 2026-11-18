<?php

namespace ACFML\Repeater\Shuffle;

trait MetaValueTrait {

	public function readOneValue( $entry ) {
		return \WPML\Utils\Unserialize::maybe( $this->firstStoredValue( $entry ) );
	}

	private function firstStoredValue( $entry ) {
		return is_array( $entry ) ? reset( $entry ) : $entry;
	}
}
