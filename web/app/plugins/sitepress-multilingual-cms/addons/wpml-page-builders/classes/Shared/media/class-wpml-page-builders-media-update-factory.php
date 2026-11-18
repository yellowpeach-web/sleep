<?php

abstract class WPML_Page_Builders_Media_Update_Factory implements IWPML_PB_Media_Update_Factory {

	protected function get_media_translate( $find_usage_instead_of_translate ) {
		if ( $find_usage_instead_of_translate ) {
			return new WPML_Page_Builders_Media_Find_Usage();
		}

		return WPML_Page_Builders_Media_Translate::create();
	}
}
