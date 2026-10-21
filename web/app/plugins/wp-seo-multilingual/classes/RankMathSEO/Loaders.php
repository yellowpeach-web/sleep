<?php

namespace WPML\WPSEO\RankMathSEO;

use WPML\FP\Logic;

class Loaders {

	public static function get() {
		global $sitepress;

		$isSitemapHrefLangEnabled = ! defined( 'WPML_SEO_ENABLE_SITEMAP_HREFLANG' ) || constant( 'WPML_SEO_ENABLE_SITEMAP_HREFLANG' );
		$langNegotiationType      = (int) $sitepress->get_setting( 'language_negotiation_type' );
		$isWooCommerceActive      = class_exists( \WooCommerce::class );

		return wpml_collect(
			[
				\WPML\WPSEO\Shared\Upgrade\Hooks::class => true,
				\WPML\WPSEO\Shared\HeadLangsNoticeHooks::class => true,
				Sitemap\Hooks::class                    => true,
				Sitemap\AlternateLangHooks::class       => $isSitemapHrefLangEnabled,
				Sitemap\HeadLangsHooks::class           => $isSitemapHrefLangEnabled,
				Sitemap\TranslatedArchivesHooks::class  => WPML_LANGUAGE_NEGOTIATION_TYPE_DOMAIN !== $langNegotiationType,
				Slugs\HooksFactory::class               => true,
				TranslationJob\Hooks::class             => true,
				Terms\Meta\Hooks::class                 => true,
				PrimaryCategory\Hooks::class            => true,
				RestHead\LanguageHooks::class           => true,
				Breadcrumbs\Hooks::class                => true,
				AuthorBase\Hooks::class                 => true,
				InstantIndexing\ManualSubmitHooks::class => WPML_LANGUAGE_NEGOTIATION_TYPE_DOMAIN !== $langNegotiationType,
				InstantIndexing\AutoSubmitHooks::class   => WPML_LANGUAGE_NEGOTIATION_TYPE_DIRECTORY === $langNegotiationType,
				Sitemap\LangMode\DirectoryHooks::class  => WPML_LANGUAGE_NEGOTIATION_TYPE_DIRECTORY === $langNegotiationType,
				Sitemap\LangMode\DomainHooks::class     => WPML_LANGUAGE_NEGOTIATION_TYPE_DOMAIN === $langNegotiationType,
				Compatibility\WooCommerce\Hooks::class  => $isWooCommerceActive,
				Compatibility\WooCommerce\CategoryBaseHooks::class => $isWooCommerceActive,
				Compatibility\WooCommerce\RestRouteHooks::class => $isWooCommerceActive,
				Compatibility\WooCommerce\PreviewQueryVarHooks::class => $isWooCommerceActive,
			]
		)->filter( Logic::isTruthy() )
			->keys()
			->toArray();
	}
}
