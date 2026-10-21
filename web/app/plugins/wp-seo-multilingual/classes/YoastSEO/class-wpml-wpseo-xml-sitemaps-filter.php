<?php

use WPML\WPSEO\YoastSEO\Utils;
use WPML\Settings\LanguageNegotiation;

class WPML_WPSEO_XML_Sitemaps_Filter implements IWPML_Action {

	protected $sitepress;

	private $wpml_url_converter;

	private $back_trace;

	private $image_parser;

	public function __construct( $sitepress, $wpml_url_converter, WPSEO_Sitemap_Image_Parser $image_parser, ?WPML_Debug_BackTrace $back_trace = null ) {
		$this->sitepress          = $sitepress;
		$this->wpml_url_converter = $wpml_url_converter;
		$this->image_parser       = $image_parser;
		$this->back_trace         = $back_trace;
	}

	public function add_hooks() {
		if ( Utils::isSitemapRequest() ) {
			$this->add_sitemap_hooks();
		}
	}

	private function add_sitemap_hooks() {
		global $wpml_query_filter;

		if ( LanguageNegotiation::isDomain() ) {
			add_filter( 'wpml_get_home_url', [ $this, 'get_home_url_filter' ], 10, 4 );
			add_filter( 'wpseo_posts_join', [ $wpml_query_filter, 'filter_single_type_join' ], 10, 2 );
			add_filter( 'wpseo_posts_where', [ $this, 'filterByCurrentLanguage' ], 10, 2 );
			add_filter( 'wpseo_typecount_join', [ $wpml_query_filter, 'filter_single_type_join' ], 10, 2 );
			add_filter( 'wpseo_typecount_where', [ $this, 'filterByCurrentLanguage' ], 10, 2 );
		} else {
			add_filter( 'wpseo_sitemap_post_type_first_links', [ $this, 'addTranslatedFirstLinks' ], 10, 2 );
			add_filter( 'wpseo_xml_sitemap_post_url', [ $this, 'exclude_hidden_language_posts' ], 10, 2 );
			add_action( 'parse_query', [ $this, 'remove_sitemap_from_non_default_languages' ] );
		}

		if ( LanguageNegotiation::isDir() ) {
			add_filter( 'wpml_get_home_url', [ $this, 'maybe_return_original_url_in_get_home_url_filter' ], 10, 2 );
		}

		add_filter( 'wpseo_build_sitemap_post_type', [ $this, 'wpseo_build_sitemap_post_type_filter' ] );
		add_filter( 'wpseo_exclude_from_sitemap_by_post_ids', [ $this, 'exclude_translations_of_static_pages' ] );
		add_filter( 'wpseo_exclude_from_sitemap_by_term_ids', [ $this, 'excludeHiddenLanguagesTerms' ] );
	}

	public function filterByCurrentLanguage( $where, $postType ) {
		global $wpdb;

		if ( ! $this->sitepress->is_translated_post_type( $postType ) ) {
			return $where;
		}

		return $where . $wpdb->prepare( ' AND wpml_translations.language_code = %s', $this->sitepress->get_current_language() );
	}

	public function addTranslatedFirstLinks( $links, $postType ) {
		if ( ! $this->sitepress->is_translated_post_type( $postType ) ) {
			return $links;
		}

		$defaultLang = $this->sitepress->get_default_language();
		$activeLangs = $this->sitepress->get_active_languages();
		unset( $activeLangs[ $defaultLang ] );

		$hasPageOnFront = (bool) $this->get_post_id_for_option( 'page_on_front', $defaultLang );
		foreach ( $activeLangs as $langCode => $langData ) {
			$url     = null;
			$lastmod = null;
			$images  = null;

			switch ( $postType ) {
				case 'page':
					$postId = $this->get_post_id_for_option( 'page_on_front', $langCode );
					if ( ! $hasPageOnFront || Utils::isIndexablePost( $postId ) ) {
						$url = $this->get_translated_home_url( $langCode );
						if ( 'page' === get_option( 'show_on_front' ) ) {
							$lastmod = get_the_modified_time( 'c', $postId );
						}
						$images = $this->get_images( $postId );
					}
					break;
				case 'post':
					$postId = $this->get_post_id_for_option( 'page_for_posts', $langCode );
					if ( Utils::isIndexablePost( $postId ) ) {
						$url = get_permalink( $postId );
					}
					break;
				case 'product':
					if ( defined( 'WC_PLUGIN_FILE' ) ) {
						$postId = $this->get_post_id_for_option( 'woocommerce_shop_page_id', $langCode );
						if ( Utils::isIndexablePost( $postId ) ) {
							$url = get_permalink( $postId );
						}
						break;
					}
				default:
					$this->sitepress->switch_lang( $langCode );
					try {
						$url = get_post_type_archive_link( $postType );
					} finally {
						$this->sitepress->switch_lang();
					}
			}

			if ( $url ) {
				$link = [
					'loc' => $url,
					'mod' => $lastmod ?? WPSEO_Sitemaps::get_last_modified_gmt( $postType ),
					'chf' => 'daily',
					'pri' => 1,
				];
				if ( $images ) {
					$link['images'] = $images;
				}
				$links[] = $link;
			}
		}

		return $links;
	}


	public function get_home_url_filter( $home_url, $url, $path, $orig_scheme ) {
		if ( 'relative' !== $orig_scheme ) {
			$home_url = $this->wpml_url_converter->convert_url( $home_url, $this->sitepress->get_current_language() );
		}
		return $home_url;
	}

	public function wpseo_build_sitemap_post_type_filter( $type ) {
		global $sitepress_settings;
		$sitepress_settings['auto_adjust_ids'] = 0;

		if ( ! LanguageNegotiation::isDomain() ) {
			remove_filter( 'terms_clauses', [ $this->sitepress, 'terms_clauses' ] );
		}

		remove_filter( 'category_link', [ $this->sitepress, 'category_link_adjust_id' ], 1 );

		return $type;
	}

	public function exclude_hidden_language_posts( $url, $post ) {
		if ( ! isset( $post->ID ) ) {
			return $url;
		}

		$hidden_languages = $this->sitepress->get_setting( 'hidden_languages', [] );

		if ( empty( $hidden_languages ) ) {
			return $url;
		}

		$language_info = $this->sitepress->post_translations()->get_element_lang_code( $post->ID );

		if ( in_array( $language_info, $hidden_languages, true ) ) {
			return null;
		}

		return $url;
	}

	public function excludeHiddenLanguagesTerms( $termIds ) {
		global $wpdb;
		$hiddenLanguages = $this->sitepress->get_setting( 'hidden_languages', [] );

		if ( empty( $hiddenLanguages ) ) {
			return $termIds;
		}

		foreach ( $hiddenLanguages as $language ) {
			$query = $wpdb->prepare(
				"SELECT wptt.term_id
				FROM {$wpdb->prefix}term_taxonomy AS wptt
				JOIN {$wpdb->prefix}icl_translations AS iclt
					ON iclt.element_id = wptt.term_taxonomy_id
				WHERE language_code=%s AND element_type like 'tax_%'",
				$language
			);
			$terms   = $wpdb->get_col( $query );

			$termIds = array_merge( $termIds, array_map( 'intval', $terms ) );
		}

		return $termIds;
	}

	public function exclude_translations_of_static_pages( $excluded_post_ids ) {
		$static_pages = [ 'page_on_front', 'page_for_posts' ];
		foreach ( $static_pages as $static_page ) {
			$page_id = (int) get_option( $static_page );
			if ( $page_id ) {
				$translations = (array) $this->sitepress->post_translations()->get_element_translations( $page_id );
				unset( $translations[ $this->sitepress->get_default_language() ] );
				$excluded_post_ids = array_merge( $excluded_post_ids, array_values( $translations ) );
			}
		}

		return $excluded_post_ids;
	}

	public function maybe_return_original_url_in_get_home_url_filter( $home_url, $original_url ) {
		if ( $home_url === $original_url ) {
			return $home_url;
		}

		$places = [
			[ 'WPSEO_Post_Type_Sitemap_Provider', 'get_parsed_home_url' ],
			[ 'WPSEO_Sitemaps_Router', 'get_base_url' ],
			[ 'WPSEO_Sitemaps_Renderer', '__construct' ],
		];

		$back_trace = $this->get_back_trace();
		$refresh    = true;
		foreach ( $places as $place ) {
			if ( $back_trace->is_class_function_in_call_stack( $place[0], $place[1], $refresh ) ) {
				return $original_url;
			}
			$refresh = false;
		}

		return $home_url;
	}

	private function get_back_trace() {
		if ( null === $this->back_trace ) {
			$this->back_trace = new WPML_Debug_BackTrace( phpversion() );
		}

		return $this->back_trace;
	}

	private function get_post_id_for_option( $option, $lang ) {
		return $this->sitepress->get_object_id( get_option( $option ), 'page', false, $lang );
	}

	private function get_translated_home_url( $lang_code ) {
		return $this->wpml_url_converter->convert_url( home_url(), $lang_code );
	}

	private function get_images( $page_id ) {
		$images = [];

		if ( apply_filters( 'wpseo_xml_sitemap_include_images', true ) ) {
			$images = $this->image_parser->get_images( get_post( $page_id ) );
		}

		return $images;
	}

	public function remove_sitemap_from_non_default_languages( &$wp_query ) {
		if (
				$wp_query->get( 'sitemap' )
				&& $this->sitepress->get_current_language() !== $this->sitepress->get_default_language()
			) {
			unset( $wp_query->query_vars['sitemap'] );
			$wp_query->set_404();
			status_header( 404 );
		}
	}
}
