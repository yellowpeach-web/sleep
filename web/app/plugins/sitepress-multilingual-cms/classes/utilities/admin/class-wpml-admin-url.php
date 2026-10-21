<?php

class WPML_Admin_URL {

	const SECTION_MAP = array(
		'1'           => 'translation-editor',
		'2'           => 'posts-pages-sync',
		'3'           => 'translated-documents',
		'7'           => 'post-types',
		'8'           => 'taxonomies',
		'media'       => 'media',
		'cf'          => 'custom-fields',
		'cf-tax'      => 'custom-term-meta',
		'custom-xml'  => 'custom-xml',
	);

	public static function multilingual_setup( $section = null ) {
		if ( ! defined( 'WPML_TM_VERSION' ) ) {
			$url = admin_url( 'admin.php?page=' . ICL_PLUGIN_FOLDER . '/menu/translation-options.php' );
			if ( $section ) {
				$url .= '#ml-content-setup-sec-' . $section;
			}
			return $url;
		}

		$section_key = $section === null ? '' : (string) $section;
		if ( isset( self::SECTION_MAP[ $section_key ] ) ) {
			return admin_url(
				'admin.php?page=' . WPML_TM_FOLDER . WPML_Translation_Management::PAGE_SLUG_SETTINGS
				. '&section=' . self::SECTION_MAP[ $section_key ]
			);
		}

		return admin_url(
			'admin.php?page=' . WPML_TM_FOLDER . WPML_Translation_Management::PAGE_SLUG_SETTINGS
		);
	}
}
