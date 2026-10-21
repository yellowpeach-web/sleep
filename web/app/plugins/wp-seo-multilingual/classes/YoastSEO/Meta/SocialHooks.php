<?php

namespace WPML\WPSEO\YoastSEO\Meta;

use WPML\FP\Obj;
use WPML\WPSEO\YoastSEO\Presentation\Hooks as PresentationHooks;
use WPML\WPSEO\YoastSEO\Utils;
use Yoast\WP\SEO\Presentations\Indexable_Presentation;

class SocialHooks implements \IWPML_Frontend_Action {

	const OPTION_KEY = 'wpseo_social';

	public function add_hooks() {
		add_filter( 'wpseo_opengraph_title', [ $this, 'translateTitle' ], PresentationHooks::OPEN_GRAPH_PRIORITY + 1, 2 );
		add_filter( 'wpseo_opengraph_desc', [ $this, 'translateDescription' ], PresentationHooks::OPEN_GRAPH_PRIORITY + 1, 2 );
	}

	public function translateTitle( $title, $presentation ) {
		return Utils::isLatestPostsFrontPage( $presentation ) ? self::translate( 'title', $title, $presentation ) : $title;
	}

	public function translateDescription( $description, $presentation ) {
		return Utils::isLatestPostsFrontPage( $presentation ) ? self::translate( 'desc', $description, $presentation ) : $description;
	}

	private static function translate( $type, $originalText, $presentation ) {
		$template = Obj::prop( 'open_graph_frontpage_' . $type, get_option( PresentationHooks::OPTION_KEY ) )
			?: Obj::prop( 'og_frontpage_' . $type, get_option( self::OPTION_KEY ) );

		return $template ? wpseo_replace_vars( $template, $presentation ) : $originalText;
	}
}
