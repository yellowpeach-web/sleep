<?php

namespace WPML\WPSEO\YoastSEO\Terms\Meta;

use WPML\LIB\WP\Hooks as WPHooks;

use function WPML\FP\spreadArgs;

class DashboardHooks implements \IWPML_Backend_Action {

	const SECTION_ID = 'stringPackage/' . Hooks::PACKAGE['kind_slug'];

	public function add_hooks() {
		WPHooks::onFilter( 'wpml_tm_dashboard_item_sections_note' )
			->then( spreadArgs( [ $this, 'addNote' ] ) );
	}

	public function addNote( array $itemSections ) {
		return array_map(
			function ( $itemSection ) {
				if ( self::SECTION_ID === $itemSection['id'] ) {
					$itemSection['note'] = esc_html__( 'This section has the Yoast SEO texts of your categories, tags and other taxonomy terms.', 'wp-seo-multilingual' );
				}

				return $itemSection;
			},
			$itemSections
		);
	}
}
