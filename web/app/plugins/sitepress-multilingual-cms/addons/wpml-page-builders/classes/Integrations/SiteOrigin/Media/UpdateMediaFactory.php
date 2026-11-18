<?php

namespace WPML\PB\SiteOrigin\Media;

use WPML\PB\SiteOrigin\DataSettings;

class UpdateMediaFactory extends \WPML_Page_Builders_Media_Update_Factory {

	public function create( $find_usage_instead_of_translate = false ) {
		global $sitepress;

		$mediaTranslate = $this->get_media_translate( $find_usage_instead_of_translate );

		return new \WPML_Page_Builders_Update_Media(
			new \WPML_Page_Builders_Update( new DataSettings() ),
			new \WPML_Translation_Element_Factory( $sitepress ),
			new NodesIterator( $mediaTranslate ),
			$find_usage_instead_of_translate
				? null
				: new \WPML_Page_Builders_Media_Usage( $mediaTranslate, new \WPML_Media_Usage_Factory() )
		);
	}
}
