<?php

namespace WPML\MediaTranslation;

class AddMediaDataToTranslationPackageFactory implements \IWPML_Backend_Action_Loader, \IWPML_REST_Action_Loader {
	public function create() {
		global $sitepress, $wpml_post_translations;

		return new AddMediaDataToTranslationPackage( new PostWithMediaFilesFactory(), $sitepress, $wpml_post_translations );
	}
}