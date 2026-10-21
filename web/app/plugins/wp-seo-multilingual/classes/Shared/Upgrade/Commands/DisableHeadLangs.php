<?php
namespace WPML\WPSEO\Shared\Upgrade\Commands;

use WPML\WPSEO\RankMathSEO\Utils as RankMathUtils;
use WPML\WPSEO\Shared\Options;
use WPML\WPSEO\Shared\HeadLangs;
use WPML\WPSEO\YoastSEO\Utils as YoastUtils;

class DisableHeadLangs implements Command {

	const KEY_COMPLETED = 'command_disable_head_langs_complete';

	public static function run() {
		if ( Options::get( self::KEY_COMPLETED ) ) {
			return;
		}

		if ( defined( 'WPSEO_VERSION' ) && YoastUtils::hasXmlSitemap( get_option( 'wpseo' ) ) ) {
			self::disableHeadLangsWithNotice();
		}

		if ( defined( 'RANK_MATH_VERSION' ) && RankMathUtils::isSitemapModuleActive() ) {
			self::disableHeadLangsWithNotice();
		}

		Options::set( self::KEY_COMPLETED, true );
	}

	private static function disableHeadLangsWithNotice() {
		if ( defined( 'WPML_SEO_ENABLE_SITEMAP_HREFLANG' ) && ! WPML_SEO_ENABLE_SITEMAP_HREFLANG ) {
			return;
		}

		if ( ! HeadLangs::turnOff() ) {
			return;
		}

		self::setNoticePending();
	}

	private static function setNoticePending() {
		Options::set( HeadLangs::KEY_NOTICE_PENDING, true );
	}
}
