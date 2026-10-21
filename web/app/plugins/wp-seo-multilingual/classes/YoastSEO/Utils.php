<?php

namespace WPML\WPSEO\YoastSEO;

use WPML\FP\Obj;
use Yoast\WP\SEO\Presentations\Indexable_Presentation;

class Utils {

	const KEY_META_TITLE       = 'wpseo_title';
	const KEY_META_DESC        = 'wpseo_desc';
	const KEY_USER_META_DESC   = 'wpseo_metadesc';
	const KEY_USER_PRONOUNS    = 'wpseo_pronouns';
	const KEY_BREADCRUMB_TITLE = 'wpseo_bctitle';
	const KEY_FOCUS_KEYWORD    = 'wpseo_focuskw';
	const KEY_SOCIAL_TITLE     = 'wpseo_opengraph-title';
	const KEY_SOCIAL_DESC      = 'wpseo_opengraph-description';
	const KEY_X_TITLE          = 'wpseo_twitter-title';
	const KEY_X_DESC           = 'wpseo_twitter-description';

	const KEY_XML_SITEMAP = 'enable_xml_sitemap';

	const OBJECT_TYPE_HOME_PAGE = 'home-page';

	private static $deprecated_hooks = [
		'wpseo_premium_post_redirect_slug_change' => [
			'since'    => '12.9.0',
			'new_name' => 'Yoast\WP\SEO\post_redirect_slug_change',
		],
		'wpseo_premium_term_redirect_slug_change' => [
			'since'    => '12.9.0',
			'new_name' => 'Yoast\WP\SEO\term_redirect_slug_change',
		],
	];

	public static function hasXmlSitemap( $options ) {
		return (bool) Obj::prop( self::KEY_XML_SITEMAP, (array) $options );
	}

	public static function add_filter( $name, $callback, $priority = 10, $arguments = 1 ) {
		if ( isset( self::$deprecated_hooks[ $name ] ) ) {
			if ( version_compare( constant( 'WPSEO_VERSION' ), self::$deprecated_hooks[ $name ]['since'], '>=' ) ) {
				$name = self::$deprecated_hooks[ $name ]['new_name'];
			}
		}

		add_filter( $name, $callback, $priority, $arguments );
	}

	public static function isPremium() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return is_plugin_active( 'wordpress-seo-premium/wp-seo-premium.php' );
	}

	public static function isLatestPostsFrontPage( $presentation ) {
		return self::OBJECT_TYPE_HOME_PAGE === Obj::path( [ 'model', 'object_type' ], $presentation );
	}

	public static function isIndexablePost( $postId ) {
		return $postId && (int) \WPSEO_Meta::get_value( 'meta-robots-noindex', $postId ) !== 1;
	}

	public static function isIndexableTerm( $term ) {
		return (int) \WPSEO_Taxonomy_Meta::get_term_meta( $term, $term->taxonomy, 'noindex' ) !== 1;
	}

	public static function isSitemapRequest(): bool {
		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return false;
		}

		$request_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		$path        = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
		$extension   = pathinfo( $path, PATHINFO_EXTENSION );

		if ( stripos( $request_uri, 'sitemap' ) !== false && in_array( $extension, [ 'xml', 'xsl' ], true ) ) {
			return true;
		}

		return false;
	}
}
