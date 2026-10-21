<?php

use ACFML\Convertable\AbstractIdsConvertable;

class WPML_ACF_Term_Ids extends AbstractIdsConvertable {

	protected function convertId( $id, WPML_ACF_Field $acfField ) {
		$translatedId = ( new WPML_ACF_Term_Id( $id, $acfField ) )->convert()->id;

		return is_string( $id ) && null !== $translatedId
			? (string) $translatedId
			: $translatedId;
	}
}
