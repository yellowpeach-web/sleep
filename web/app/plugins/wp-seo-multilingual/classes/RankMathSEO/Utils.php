<?php
namespace WPML\WPSEO\RankMathSEO;

use RankMath\Helper;

class Utils {

	const MODULES_OPTION = 'rank_math_modules';

	const SITEMAP_MODULE = 'sitemap';

	const GENERAL_SETTINGS_OPTION = 'rank-math-options-general';

	const REMOVE_PRODUCT_BASE_KEY = 'wc_remove_product_base';

	public static function isSitemapModuleActive() {
		return self::hasSitemapModule( get_option( self::MODULES_OPTION, [] ) );
	}

	public static function hasSitemapModule( $modules ) {
		return in_array( self::SITEMAP_MODULE, (array) $modules, true );
	}

	public static function isGeneralSettingOn( string $settingKey ): bool {
		$settings = (array) get_option( self::GENERAL_SETTINGS_OPTION, [] );

		return 'on' === ( $settings[ $settingKey ] ?? null );
	}

	public static function isProductBaseRemoved(): bool {
		return self::isGeneralSettingOn( self::REMOVE_PRODUCT_BASE_KEY );
	}

	public static function isIndexablePost( $postId ) {
		return Helper::is_post_indexable( $postId );
	}

	public static function isIndexableTerm( $term ) {
		return Helper::is_term_indexable( $term );
	}

	public static function getBreadcrumbsHomeLink() {
		return Helper::get_settings( 'general.breadcrumbs_home_link', home_url() );
	}
}
