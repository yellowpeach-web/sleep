<?php

namespace ACFML\Tools;

use WPML\UIPage;

class AdminUrl {

	const DASHBOARD_PARAM_SECTIONS          = 'sections';
	const DASHBOARD_SECTION_STRING          = 'string';
	const DASHBOARD_PARAM_STRING_DOMAIN     = 'predefinedStringDomain';
	const DASHBOARD_SECTION_PACKAGE_BY_SLUG = 'stringPackage/%s';

	const JOBS_PARAM_ELEMENT_TYPE = 'element_type';

	private static function getWPMLTMDashboard( array $sections = [], string $stringDomain = '' ) : string {
		$dashboardUrl = admin_url( UIPage::getTMDashboard() );
		if ( empty( $sections ) ) {
			return $dashboardUrl;
		}

		$dashboardUrl = add_query_arg(
			[ self::DASHBOARD_PARAM_SECTIONS => implode( ',', $sections ) ],
			$dashboardUrl
		);

		if ( in_array( self::DASHBOARD_SECTION_STRING, $sections, true ) && $stringDomain ) {
			$dashboardUrl = add_query_arg(
				[ self::DASHBOARD_PARAM_STRING_DOMAIN => $stringDomain ],
				$dashboardUrl
			);
		}

		return $dashboardUrl;
	}

	public static function getWPMLTMDashboardPackageSection( string $packageKindSlug ) : string {
		$section = sprintf( self::DASHBOARD_SECTION_PACKAGE_BY_SLUG, $packageKindSlug );
		return self::getWPMLTMDashboard( [ $section ] );
	}

	public static function getTranslationDashboard() : string {
		return self::getWPMLTMDashboard();
	}

	public static function getTranslationJobs( string $elementType ): string {
		return add_query_arg( [ self::JOBS_PARAM_ELEMENT_TYPE => $elementType ], admin_url( UIPage::getTMJobs() ) );
	}

	public static function getFieldGroupsList(): string {
		return admin_url( 'edit.php?post_type=acf-field-group' );
	}

	public static function getCustomFieldsTranslationSettings(): string {
		return admin_url( 'admin.php?page=tm/menu/settings#ml-content-setup-sec-cf' );
	}

}
