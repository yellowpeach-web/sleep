<?php

namespace ACFML\Convertable;

class UrlFieldData extends AbstractUrlConvertable {

	public function convert( \WPML_ACF_Field $acf_field ) {
		return $this->translateUrl( $acf_field->meta_value, $this->resolveTargetLang( $acf_field ) );
	}
}
