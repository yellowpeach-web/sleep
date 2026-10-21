<?php

namespace WPML\WPSEO\YoastSEO\Sitemap;

use WPML\WPSEO\Shared\HeadLangs;
use WPML\WPSEO\YoastSEO\Utils;

class HeadLangsHooks implements \IWPML_Backend_Action, \IWPML_Frontend_Action {

	public function add_hooks() {
		add_action( 'update_option_wpseo', [ $this, 'turnHeadLangsBackOnWithoutSitemap' ], 10, 2 );
	}

	public function turnHeadLangsBackOnWithoutSitemap( $oldOptions, $options ) {
		if ( Utils::hasXmlSitemap( $oldOptions ) && ! Utils::hasXmlSitemap( $options ) ) {
			HeadLangs::turnBackOn();
		}
	}
}
