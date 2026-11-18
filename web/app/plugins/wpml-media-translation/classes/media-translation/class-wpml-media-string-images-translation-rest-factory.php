<?php

class WPML_Media_String_Images_Translation_REST_Factory implements IWPML_REST_Action_Loader {

	public function create() {
		if ( is_admin() ) {
			return null;
		}

		return ( new WPML_Media_String_Images_Translation_Factory() )->create();
	}

}
