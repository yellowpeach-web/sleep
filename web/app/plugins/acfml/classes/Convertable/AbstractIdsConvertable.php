<?php

namespace ACFML\Convertable;

abstract class AbstractIdsConvertable implements \WPML_ACF_Convertable {

	public function convert( \WPML_ACF_Field $acfField ) {
		$cameSerialized = is_serialized( $acfField->meta_value );

		$ids = $cameSerialized
			? maybe_unserialize( $acfField->meta_value )
			: $acfField->meta_value;

		$translatedIds = $this->convertSingleOrArray( $ids, $acfField );

		return $cameSerialized
			? serialize( $translatedIds )
			: $translatedIds;
	}

	abstract protected function convertId( $id, \WPML_ACF_Field $acfField );

	private function convertSingleOrArray( $ids, \WPML_ACF_Field $acfField ) {
		if ( is_array( $ids ) ) {
			return array_map( fn( $id ) => $this->convertIfId( $id, $acfField ), $ids );
		}

		return $this->convertIfId( $ids, $acfField );
	}

	private function convertIfId( $value, \WPML_ACF_Field $acfField ) {
		return self::isId( $value )
			? $this->convertId( $value, $acfField )
			: $value;
	}

	private static function isId( $value ): bool {
		return is_numeric( $value ) && 0 !== (int) $value;
	}
}
