<?php

namespace WPML\WPSEO\RankMathSEO\Sitemap;

use WPML\WPSEO\RankMathSEO\Utils;
use WPML\WPSEO\Shared\HeadLangs;

class HeadLangsHooks implements \IWPML_Backend_Action, \IWPML_Frontend_Action {

	public function add_hooks() {
		add_action( 'update_option_' . Utils::MODULES_OPTION, [ $this, 'turnHeadLangsBackOnWithoutSitemap' ], 10, 2 );
	}

	public function turnHeadLangsBackOnWithoutSitemap( $oldModules, $modules ) {
		if ( Utils::hasSitemapModule( $oldModules ) && ! Utils::hasSitemapModule( $modules ) ) {
			HeadLangs::turnBackOn();
		}
	}
}
