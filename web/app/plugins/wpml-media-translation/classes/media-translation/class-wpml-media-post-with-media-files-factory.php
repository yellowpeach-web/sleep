<?php

class WPML_Media_Post_With_Media_Files_Factory {
	public function create( $post_id, $attached_media_ids = null ) {
		global $sitepress, $iclTranslationManagement;

		return new WPML_Media_Post_With_Media_Files(
			$post_id,
			new \WPML\Media\Factories\WPML_Media_Element_Parser_Factory(),
			new WPML_Media_Attachment_By_URL_Factory(),
			$sitepress,
			new WPML_Custom_Field_Setting_Factory( $iclTranslationManagement ),
			new \WPML\Media\Factories\WPML_Media_Attachment_By_URL_Query_Factory(),
			$attached_media_ids
		);
	}
}
