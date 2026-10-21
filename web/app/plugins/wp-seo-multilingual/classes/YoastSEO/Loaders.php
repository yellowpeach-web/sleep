<?php

namespace WPML\WPSEO\YoastSEO;

use WPML\FP\Logic;

class Loaders {

	public static function get( $wpSeoVersion ) {
		$isSitemapHrefLangEnabled = ! defined( 'WPML_SEO_ENABLE_SITEMAP_HREFLANG' ) || constant( 'WPML_SEO_ENABLE_SITEMAP_HREFLANG' );
		$isSTEnabled              = defined( 'WPML_ST_VERSION' );
		$isWPSEONewsEnabled       = defined( 'WPSEO_NEWS_VERSION' );
		$isGTEVersion14           = version_compare( $wpSeoVersion, '14', '>=' );
		$isWPSEOPremium           = Utils::isPremium();

		return wpml_collect(
			[
				\WPML\WPSEO\Shared\Upgrade\Hooks::class   => true,
				\WPML\WPSEO\Shared\HeadLangsNoticeHooks::class => true,
				\WPML_WPSEO_Main_Factory::class           => true,
				PrimaryCategory\Hooks::class              => true,
				Meta\CopyFieldsSyncHooks::class           => true,
				BulkEditor\TranslationStatusHooks::class  => true,
				Terms\CopyOnceHooks::class                => true,
				Terms\Meta\Hooks::class                   => true,
				Terms\Meta\TermJobIntegration::class      => true,
				TranslationJob\Hooks::class               => true,
				Editor\ContentLocaleHooks::class          => true,
				Sitemap\Cache::class                      => true,
				Sitemap\PerLanguageSitemapsNotice::class  => true,
				Sitemap\AlternateLangHooks::class         => $isSitemapHrefLangEnabled,
				Sitemap\HeadLangsHooks::class             => $isSitemapHrefLangEnabled,
				SlugTranslation\Hooks::class              => $isSTEnabled,
				Terms\Meta\AdminScreenHooks::class        => $isSTEnabled,
				Terms\Meta\DashboardHooks::class          => $isSTEnabled,
				Addons\WPSEONews\Sitemap\Hooks::class     => $isWPSEONewsEnabled,
				Presentation\Hooks::class                 => $isGTEVersion14,
				Presentation\SchemaPieceLanguageHooks::class => $isGTEVersion14,
				RestHead\LanguageHooks::class             => $isGTEVersion14,
				Meta\SocialHooks::class                   => $isGTEVersion14,
				Indexable\Hooks::class                    => $isGTEVersion14,
				Indexable\PostPermalinkHooks::class       => $isGTEVersion14,
				Indexable\LinkHomeUrlHooks::class         => $isGTEVersion14,
				Redirects\Hooks::class                    => $isWPSEOPremium,
				Redirects\ShouldCreateTermRedirect::class => $isWPSEOPremium,
				Redirects\PostRedirectLanguage::class     => $isWPSEOPremium,
			]
		)->filter( Logic::isTruthy() )
			->keys()
			->toArray();
	}
}
