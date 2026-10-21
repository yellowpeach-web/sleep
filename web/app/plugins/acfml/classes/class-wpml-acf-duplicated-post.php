<?php

class WPML_ACF_Duplicated_Post extends \ACFML\Field\Resolver {

	public function resolve_field( \WPML_ACF_Processed_Data $processed_data ) {
		return $this->run( $processed_data );
	}

}
