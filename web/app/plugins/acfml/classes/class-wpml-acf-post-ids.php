<?php

use ACFML\Convertable\AbstractIdsConvertable;

class WPML_ACF_Post_Ids extends AbstractIdsConvertable {

	protected function convertId( $id, WPML_ACF_Field $acfField ) {
		return (string) ( new WPML_ACF_Post_Id( $id, $acfField ) )->convert()->id;
	}
}
