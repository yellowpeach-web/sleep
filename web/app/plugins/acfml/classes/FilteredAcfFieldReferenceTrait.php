<?php

namespace ACFML;

trait FilteredAcfFieldReferenceTrait {

	public function normalizeAcfFieldReference( $reference ) {
		if ( ! is_string( $reference ) || '' === $reference ) {
			return $reference;
		}

		preg_match( '/(field_[a-zA-Z0-9]+)$/', $reference, $matches );

		return $matches[1] ?? $reference;
	}

	private function getFieldObjectWithFilteredReference( $metaKey, $objectFromId, $formatValue = null, $loadValue = null ) {
		$args = [ $metaKey, $objectFromId ];
		if ( null !== $formatValue ) {
			$args[] = $formatValue;
			if ( null !== $loadValue ) {
				$args[] = $loadValue;
			}
		}

		add_filter( 'acf/load_reference', [ $this, 'normalizeAcfFieldReference' ] );
		$field = call_user_func_array( 'get_field_object', $args );
		remove_filter( 'acf/load_reference', [ $this, 'normalizeAcfFieldReference' ] );

		return $field;
	}

	private function getTopLevelField( $metaKey, $objectId ) {
		if ( ! is_string( $metaKey ) || '' === $metaKey || '_' === $metaKey[0] ) {
			return null;
		}

		$field = $this->getFieldObjectWithFilteredReference( $metaKey, $objectId, false, false );

		return is_array( $field ) && $metaKey === ( $field['name'] ?? null ) ? $field : null;
	}

}
