<?php

namespace WPML\MediaTranslation;

class ExcludeTranslatedMediaFromAutomaticJobFactory implements \IWPML_Backend_Action_Loader, \IWPML_REST_Action_Loader {
	public function create() {
		global $sitepress;

		return new ExcludeTranslatedMediaFromAutomaticJob( $sitepress );
	}
}
