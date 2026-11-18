<?php

class WPML_Media_Set_Posts_Media_Flag_REST_Factory implements IWPML_REST_Action_Loader {

	public function create() {
		if ( ! wpml_is_rest_request() ) {
			return null;
		}

		return ( new WPML_Media_Set_Posts_Media_Flag_Factory() )->create();
	}

}
