<?php

use WPML\Blocks\AttributeUrls;
use WPML\Core\Component\Translation\Domain\Links\CollectorInterface;
use WPML\FP\Lst;
use WPML\FP\Str;

class AbsoluteLinks {
	public $custom_post_query_vars = [];

	public $taxonomies_query_vars = [];

	private $active_languages;

	private $url_language_codes = [];

	private $query_vars_initialized = false;

	private $rule_match_memo = [];

	private $batching_disabled_cache;

	private $shape_env;

	private $rule_bucket_index;

	private $shape_memo = [];

	private $prefetched_pages = [];

	private $prefetched_page_misses = [];

	private $prefetched_ids = [];

	private $prefetched_terms = [];

	private $verbose_page_paths = [];

	private $verbose_page_probe;

	public function init_query_vars() {
		global $wp_post_types, $wp_taxonomies;

		if ( $this->query_vars_initialized ) {
			return;
		}

		foreach ( $wp_post_types as $k => $v ) {
			if ( 'post' === $k || 'page' === $k ) {
				continue;
			}
			if ( $v->query_var ) {
				$this->custom_post_query_vars[ $k ] = $v->query_var;
			}
		}
		foreach ( $wp_taxonomies as $k => $v ) {
			if ( 'category' === $k ) {
				continue;
			}
			if ( 'post_tag' === $k && ! $v->query_var ) {
				$tag_base     = get_option( 'tag_base', 'tag' );
				$v->query_var = $tag_base;
			}
			if ( $v->query_var ) {
				$this->taxonomies_query_vars[ $k ] = $v->query_var;
			}
		}

		$this->query_vars_initialized = true;
	}

	public static function has_href_attribute( $text ) {
		if ( is_null( $text ) ) {
			return false;
		}

		return strpos( $text, ' href=' ) > 1;
	}

	public static function has_embed_block( $text ) {
		if ( ! is_string( $text ) ) {
			return false;
		}

		return false !== strpos( $text, '<!-- wp:embed ' ) || false !== strpos( $text, '<!-- wp:core/embed ' );
	}

	public static function has_block_attribute_url( $text ) {
		if ( ! is_string( $text ) || false === strpos( $text, '<!-- wp:' ) ) {
			return false;
		}

		return (bool) preg_match( '#<!-- wp:\S+ \{.*?https?://#s', $text );
	}

	private function convert_block_attribute_urls( $text, &$alp_broken_links, ?CollectorInterface $collector = null, $language_scope = null ) {
		if ( ! self::has_embed_block( $text ) && ! self::has_block_attribute_url( $text ) ) {
			return $text;
		}

		$convert_block = function ( $matches ) use ( &$alp_broken_links, $collector, $language_scope ) {
			$attributes = json_decode( $matches[2], true );

			if ( ! is_array( $attributes ) ) {
				return $matches[0];
			}

			$block = $matches[0];

			foreach ( AttributeUrls::fromAttributes( $attributes ) as $url ) {
				$converted_url = $this->convert_single_url( $url, $alp_broken_links, $collector, $language_scope );

				if ( ! $converted_url || $converted_url === $url ) {
					continue;
				}

				$block = AttributeUrls::replaceInBlock( $block, $url, $converted_url );
			}

			return $block;
		};

		foreach (
			[
				'#<!-- wp:(\S+) (\{.*?\}) -->.*?<!-- /wp:\1 -->#s',
				'#<!-- wp:(\S+) (\{.*?\})\s*/-->#s',
			] as $pattern
		) {
			$converted = preg_replace_callback( $pattern, $convert_block, $text );
			$text      = null === $converted ? $text : $converted;
		}

		return $text;
	}

	private function convert_single_url( $url, &$alp_broken_links, ?CollectorInterface $collector = null, $language_scope = null ) {
		global $sitepress;

		$converted_markup = $this->_process_generic_text(
			'<a href="' . $url . '">.</a>',
			$alp_broken_links,
			false,
			$collector,
			$language_scope
		);

		if ( is_string( $converted_markup ) && class_exists( 'WPML_Absolute_To_Permalinks' ) ) {
			$permalinks_converter = new WPML_Absolute_To_Permalinks( $sitepress );
			$converted_markup     = $permalinks_converter->convert_text( $converted_markup );

			if ( $permalinks_converter->met_gone_target() ) {
				return false;
			}
		}

		if ( preg_match( '@href="([^"]+)"@', (string) $converted_markup, $matches ) ) {
			return $matches[1];
		}

		return false;
	}

	public static function has_href_attribute_outside_blocks( $text ) {
		if ( ! self::has_href_attribute( $text ) ) {
			return false;
		}

		if ( strpos( $text, '<!-- ' ) === false ) {
			return true;
		}

		$block_protector     = new \WPML\AbsoluteLinks\BlockProtector();
		$text_without_blocks = $block_protector->protect( $text );

		return self::has_href_attribute( $text_without_blocks );
	}

	private function batching_disabled() {
		if ( null === $this->batching_disabled_cache ) {
			$this->batching_disabled_cache = (bool) apply_filters( 'wpml_links_use_legacy_engine', false );
		}

		return $this->batching_disabled_cache;
	}

	public function _process_generic_text(
		$source_text,
		&$alp_broken_links,
		$ignore_blocks = true,
		?CollectorInterface $collector = null,
		$language_scope = null
	) {
		if ( ! is_array( $alp_broken_links ) ) {
			$alp_broken_links = [];
		}

		if ( ! self::has_href_attribute( $source_text )
			 && ! self::has_embed_block( $source_text )
			 && ! self::has_block_attribute_url( $source_text )
		) {
			return $source_text;
		}

		global $wpdb, $wp_rewrite, $sitepress, $sitepress_settings;

		$this->init_query_vars();

		$sitepress_settings = $sitepress->get_settings();
		$url_format         = WPML_URL_Format::of( $sitepress_settings );

		$default_language = $sitepress->get_default_language();
		$current_language = $sitepress->get_current_language();

		$cache_key_args = [
			$default_language,
			$current_language,
			md5( $source_text ),
		];
		if ( is_array( $language_scope ) ) {
			$cache_key_args[] = array_values( $language_scope );
		}
		$cache_key      = md5( (string) wp_json_encode( $cache_key_args ) );
		$cache_group    = '_process_generic_text';
		$found          = false;

		if ( null === $collector ) {
			$cached = WPML_Non_Persistent_Cache::get( $cache_key, $cache_group, $found );

			if ( $found && self::is_cache_entry( $cached ) ) {
				self::merge_broken_links( $alp_broken_links, $cached['broken'] );

				return $cached['text'];
			}
		}

		$broken_links_before = $alp_broken_links;

		$source_text = $this->convert_block_attribute_urls( $source_text, $alp_broken_links, $collector, $language_scope );

		$filtered_icl_post_language = filter_input( INPUT_POST, 'icl_post_language', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		if ( $ignore_blocks ) {
			$block_protector = new \WPML\AbsoluteLinks\BlockProtector();
			$text = $block_protector->protect( $source_text );

			if ( ! self::has_href_attribute( $source_text ) ) {
				if ( null === $collector ) {
					self::cache_result( $cache_key, $cache_group, $source_text, array_diff_key( $alp_broken_links, $broken_links_before ) );
				}

				return $source_text;
			}
		} else {
			$text = $source_text;
		}

		$all_active_languages     = array_keys( $sitepress->get_active_languages() );
		$this->active_languages   = $all_active_languages;
		$this->url_language_codes = $this->get_url_language_codes( $all_active_languages );
		$languages_to_process   = is_array( $language_scope )
			? array_values( array_intersect( $language_scope, $all_active_languages ) )
			: $all_active_languages;

		if ( [] === $languages_to_process ) {
			return $source_text;
		}

		$current_language = empty( $filtered_icl_post_language ) ? $current_language : $filtered_icl_post_language;
		if ( ! is_array( $language_scope ) && ! empty( $current_language ) ) {
			$key = array_search( $current_language, $languages_to_process, true );
			if ( false !== $key ) {
				unset( $languages_to_process[ $key ] );
			}
			array_unshift( $languages_to_process, $current_language );
		}

		$blacklist_requests = new WPML_Absolute_Links_Blacklist(
			apply_filters( 'wpml_sl_blacklist_requests', [], $sitepress )
		);

		$this->batching_disabled_cache = null;
		$this->rule_match_memo         = [];
		$this->shape_memo              = [];
		$this->rule_bucket_index       = null;
		$this->shape_env               = null;
		$this->prefetched_pages       = [];
		$this->prefetched_page_misses = [];
		$this->prefetched_ids         = [];
		$this->prefetched_terms       = [];
		$this->verbose_page_paths     = [];
		$this->verbose_page_probe     = null;
		$this->prefetch_link_targets( $text, $sitepress_settings, $default_language, $blacklist_requests, $current_language );

		foreach ( $languages_to_process as $test_language ) {
			$rewrite_language = is_array( $language_scope ) ? $test_language : $current_language;
			$rewrite          = $this->initialize_rewrite(
				$rewrite_language,
				$default_language,
				$sitepress,
				is_array( $language_scope ) ? [ $test_language ] : null
			);

			list( $site_domain, $domain_pattern ) = $this->language_domain_pattern( $test_language, $sitepress_settings, $default_language, $sitepress );

			$int1 = preg_match_all( '@<a([^>]*)href="((https?://' . $domain_pattern . ')?/([^"^>^\[^\]]+))"([^>]*)>@i', $text, $alp_matches1 );
			$int2 = preg_match_all( '@<a([^>]*)href=\'((https?://' . $domain_pattern . ')?/([^\'^>^\[^\]]+))\'([^>]*)>@i', $text, $alp_matches2 );

			$alp_matches = [];
			for ( $i = 0; $i < 6; $i++ ) {
				$alp_matches[ $i ] = array_merge( (array) $alp_matches1[ $i ], (array) $alp_matches2[ $i ] );
			}

			if ( $int1 || $int2 ) {
				$def_url           = [];
				$url_parts         = wp_parse_url( $this->get_home_url_with_no_lang_directory() );
				$url_parts['path'] = isset( $url_parts['path'] ) ? $url_parts['path'] : '';
				foreach ( $alp_matches[4] as $k => $dir_path ) {
					if ( $this->is_content_directory_path( $dir_path ) ) {
						continue;
					}

					list( $lang, $dir_path ) = $this->extract_lang_from_path( $sitepress_settings, $default_language, $dir_path );

					list( $request, $req_uri, $req_uri_params, $anchor_output, $home_path ) = $this->shape_link_request( $dir_path );

					if ( ! $request || $blacklist_requests->is_blacklisted( $request ) ) {
						continue;
					}

					list( $permalink_query_vars, $matched_rule_query ) = $this->match_rewrite_rules( $rewrite, $request, $req_uri );

					if ( null !== $matched_rule_query ) {
						$permalink_query_vars = apply_filters( 'wpml_absolute_links_permalink_query_vars', $permalink_query_vars, $matched_rule_query, $test_language );
					}

					$post_name             = false;
					$category_name         = false;
					$tax_name              = false;
					$try_cpt_slug_fallback = false;

					if ( isset( $permalink_query_vars['p'] ) && ! empty( $permalink_query_vars['p'] ) ) {
						list( $post_type, $post_name ) = $this->get_post_type_and_name_from_post_id( $permalink_query_vars['p'] );
					} elseif ( isset( $permalink_query_vars['pagename'] ) ) {
						$get_page_by_path = new WPML_Get_Page_By_Path( $wpdb, $sitepress, new WPML_Debug_BackTrace( null, 7 ) );

						$original_page_name               = $permalink_query_vars['pagename'];
						$permalink_query_vars['pagename'] = $this->maybe_extract_page_name( $permalink_query_vars['pagename'], $sitepress_settings, $wp_rewrite );
						$page_by_path                     = $this->get_page_by_path_with_original_fallback(
							$get_page_by_path,
							$permalink_query_vars['pagename'],
							$original_page_name,
							$test_language
						);

						if ( ! empty( $page_by_path->post_type ) ) {
							$post_type = 'page';
							$post_name = $original_page_name;
						} else {
							$post_type             = 'post';
							$post_name             = $permalink_query_vars['pagename'];
							$try_cpt_slug_fallback = true;
						}
					} elseif ( isset( $permalink_query_vars['name'] ) ) {
						$post_name = $permalink_query_vars['name'];
						$post_type = 'post';
						$try_cpt_slug_fallback = ! empty( $wp_rewrite->use_verbose_page_rules );
					} elseif ( isset( $permalink_query_vars['category_name'] ) ) {
						$category_name = $permalink_query_vars['category_name'];
					} else {
						foreach ( $this->custom_post_query_vars as $query_vars_key => $query_vars_value ) {
							if ( isset( $permalink_query_vars[ $query_vars_value ] ) ) {
								$post_name = $permalink_query_vars[ $query_vars_value ];
								$post_type = $query_vars_key;
								break;
							}
						}
						foreach ( $this->taxonomies_query_vars as $query_vars_value ) {
							if ( isset( $permalink_query_vars[ $query_vars_value ] ) ) {
								$tax_name = $permalink_query_vars[ $query_vars_value ];
								$tax_type = $query_vars_value;
								break;
							}
						}
					}

					if ( $post_name && isset( $post_type ) ) {
						$get_page_by_path = new WPML_Get_Page_By_Path( $wpdb, $sitepress, new WPML_Debug_BackTrace( null, 7 ) );
						$p                = $this->page_by_path_prefetched( $get_page_by_path, $post_name, $test_language, OBJECT, $post_type );

						if ( empty( $p ) ) {
							$fail_safe_key = $home_path . '/' . $post_name . '|' . $test_language;
							if ( $this->batching_disabled() || ! \WPML\AbsoluteLinks\NegativeLinkCache::is_known_miss( $fail_safe_key ) ) {
								$switchLang = new WPML_Temporary_Switch_Language( $sitepress, $test_language );
								remove_filter( 'url_to_postid', array( $sitepress, 'url_to_postid' ) );
								$post_id = url_to_postid( $home_path . '/' . $post_name );
								add_filter( 'url_to_postid', array( $sitepress, 'url_to_postid' ) );
								$switchLang->restore_lang();

								if ( $post_id ) {
									$p = get_post( $post_id );
								} elseif ( ! $this->batching_disabled() ) {
									\WPML\AbsoluteLinks\NegativeLinkCache::remember_miss( $fail_safe_key );
								}
							}
						}

						if ( empty( $p ) && $try_cpt_slug_fallback ) {
							$custom_post = $this->find_custom_post_type_by_slug( $post_name, $test_language );

							if ( $custom_post ) {
								$p = get_post( $custom_post->ID );
							}
						}

						if ( $p ) {
							$offsite_url = get_post_meta( $p->ID, '_cms_nav_offsite_url', true );
							if ( 'page' === $p->post_type && $offsite_url ) {
								$def_url = $this->get_regex_replacement_offline(
									$def_url,
									$offsite_url,
									$url_format,
									$lang,
									$dir_path,
									$site_domain,
									$anchor_output
								);
							} elseif ( ! $this->is_pagination_in_post( $dir_path, $post_name ) ) {
								$collector
									? $collector->addItemByIdAndType( (int) $p->ID, 'post' )
									: null;
								$def_url = $this->get_regex_replacement(
									$def_url,
									'page' === $p->post_type ? 'page_id' : 'p',
									$p->ID,
									$url_format,
									$lang,
									$dir_path,
									$site_domain,
									$url_parts,
									$this->with_page_number( $req_uri_params, $permalink_query_vars ),
									$anchor_output
								);
							}
						} else {
							$alp_broken_links[ $alp_matches[2][ $k ] ] = [];

							$name = wpml_like_escape( $post_name );
							$p    = $this->_get_ids_and_post_types( $name );
							if ( $p ) {
								foreach ( $p as $post_suggestion ) {
									if ( 'page' === $post_suggestion->post_type ) {
										$qvid = 'page_id';
									} else {
										$qvid = 'p';
									}
									$alp_broken_links[ $alp_matches[2][ $k ] ]['suggestions'][] = [
										'absolute' => '/' . ltrim( $url_parts['path'], '/' ) . '?' . $qvid . '=' . $post_suggestion->ID,
										'perma'    => '/' . ltrim( str_replace( site_url(), '', (string) get_permalink( $post_suggestion->ID ) ), '/' ),
									];
								}
							}
						}
					} elseif ( $category_name ) {
						if ( is_string( $category_name ) && false !== strpos( $category_name, '/' ) ) {
							$splits             = explode( '/', $category_name );
							$category_name      = array_pop( $splits );
							$category_parent    = array_pop( $splits );
							$category_parent_id = $wpdb->get_var( $wpdb->prepare( "SELECT term_id FROM {$wpdb->terms} WHERE slug=%s", $category_parent ) );
							$c                  = $wpdb->get_row( $wpdb->prepare( "SELECT t.term_id FROM {$wpdb->terms} t JOIN {$wpdb->term_taxonomy} x ON x.term_id=t.term_id AND x.taxonomy='category' AND x.parent=%d AND t.slug=%s", $category_parent_id, $category_name ) );
						} else {
							$c = isset( $this->prefetched_terms[ $category_name ] )
								? (object) [ 'term_id' => $this->prefetched_terms[ $category_name ] ]
								: $wpdb->get_row( $wpdb->prepare( "SELECT term_id FROM {$wpdb->terms} WHERE slug=%s", $category_name ) );
						}
						if ( $c ) {
							$collector
								? $collector->addItemByIdAndType( (int) $c->term_id, 'term' )
								: null;
							$def_url = $this->get_regex_replacement(
								$def_url,
								'cat_ID',
								$c->term_id,
								$url_format,
								$lang,
								$dir_path,
								$site_domain,
								$url_parts,
								$req_uri_params,
								$anchor_output
							);
						} elseif ( isset( $name ) ) {
							$alp_broken_links[ $alp_matches[2][ $k ] ] = [];

							$c = $wpdb->get_results(
								$wpdb->prepare( "SELECT term_id FROM {$wpdb->terms} WHERE slug LIKE %s", [ $name . '%' ] )
							);
							if ( $c ) {
								foreach ( $c as $cat_suggestion ) {
									$perma = '/' . ltrim( str_replace( get_home_url(), '', get_category_link( $cat_suggestion->term_id ) ), '/' );

									$alp_broken_links[ $alp_matches[2][ $k ] ]['suggestions'][] = [
										'absolute' => '?cat_ID=' . $cat_suggestion->term_id,
										'perma'    => $perma,
									];
								}
							}
						}
					} elseif ( $tax_name && isset( $tax_type ) ) {
						$collector && is_string( $tax_name )
						? $collector->addItemByIdAndType(
							(int) $this->maybeStripParentTerm( $tax_name ),
							'term'
						)
						: null;
						$def_url = $this->get_regex_replacement(
							$def_url,
							$tax_type,
							$tax_name,
							$url_format,
							$lang,
							$dir_path,
							$site_domain,
							$url_parts,
							$req_uri_params,
							$anchor_output
						);
					} else {
						$archive_url = $this->get_archive_url_in_language( $permalink_query_vars, $rewrite_language );

						if ( $archive_url ) {
							$def_url = $this->get_regex_replacement_url(
								$def_url,
								$archive_url,
								$url_format,
								$lang,
								$dir_path,
								$site_domain,
								$req_uri_params,
								$anchor_output
							);
						}
					}
				}

				if ( ! empty( $def_url ) ) {
					$text = preg_replace( array_keys( $def_url ), array_values( $def_url ), $text );
				}

				$tx_qvs   = ! empty( $this->taxonomies_query_vars ) && is_array( $this->taxonomies_query_vars ) ? '|' . join( '|', $this->taxonomies_query_vars ) : '';
				$post_qvs = ! empty( $this->custom_post_query_vars ) && is_array( $this->custom_post_query_vars )
					? '|' . join( '|', $this->custom_post_query_vars )
					: '';
				$home_domain         = $this->get_url_without_scheme( get_home_url() );
				$home_domain_pattern = WPML_Same_Site_Url_Normalizer::get_domain_regex_pattern( $home_domain );
				$int = preg_match_all(
					'@href=[\'"](https?://' . $home_domain_pattern . '/?\?(p|page_id' . $tx_qvs . $post_qvs . ')=([0-9a-z-]+)(#.+)?)[\'"]@i',
					$text, $matches2
				);
				if ( $int ) {
					$url_parts = wp_parse_url( rtrim( get_home_url(), '/' ) . '/' );
					$text      = preg_replace( '@href=[\'"](https?://' . $home_domain_pattern . '/?\?(p|page_id' . $tx_qvs . $post_qvs . ')=([0-9a-z-]+)(#.+)?)[\'"]@i', 'href="/' . ltrim( $url_parts['path'], '/' ) . '?$2=$3$4"', $text );
				}
			}
		}

		if ( isset( $block_protector ) ) {
			$text = $block_protector->unProtect( $text );
		}

		if ( null === $collector ) {
			self::cache_result( $cache_key, $cache_group, $text, array_diff_key( $alp_broken_links, $broken_links_before ) );
		}

		return $text;
	}

	private static function cache_result( $cache_key, $cache_group, $text, array $broken_links ) {
		WPML_Non_Persistent_Cache::set(
			$cache_key,
			[
				'text'   => $text,
				'broken' => $broken_links,
			],
			$cache_group
		);
	}

	private static function is_cache_entry( $cached ) {
		return is_array( $cached )
			&& array_key_exists( 'text', $cached )
			&& array_key_exists( 'broken', $cached )
			&& is_array( $cached['broken'] );
	}

	private static function merge_broken_links( array &$alp_broken_links, array $broken_links ) {
		foreach ( $broken_links as $url => $data ) {
			$alp_broken_links[ $url ] = $data;
		}
	}

	private function page_by_path_prefetched( $get_page_by_path, $page_name, $language, $output = OBJECT, $post_type = 'page' ) {
		$key = $page_name . '|' . $language . '|' . $post_type;

		if ( isset( $this->prefetched_pages[ $key ] ) ) {
			return get_post( $this->prefetched_pages[ $key ], $output );
		}

		if ( isset( $this->prefetched_page_misses[ $key ] ) ) {
			return null;
		}

		return $get_page_by_path->get( $page_name, $language, $output, $post_type );
	}

	private function prefetch_link_targets( $text, $sitepress_settings, $default_language, $blacklist_requests, $current_language ) {
		global $wpdb, $sitepress;

		if ( $this->batching_disabled() ) {
			return;
		}

		$rewrite = $this->initialize_rewrite( $current_language, $default_language, $sitepress );

		$paths = [];
		foreach ( $this->active_languages as $test_language ) {
			list( , $domain_pattern ) = $this->language_domain_pattern( $test_language, $sitepress_settings, $default_language, $sitepress );
			if ( preg_match_all( '@<a([^>]*)href="((https?://' . $domain_pattern . ')?/([^"^>^\[^\]]+))"([^>]*)>@i', $text, $m1 ) ) {
				foreach ( $m1[4] as $matched_path ) {
					$paths[ $matched_path ] = true;
				}
			}
			if ( preg_match_all( '@<a([^>]*)href=\'((https?://' . $domain_pattern . ')?/([^\'^>^\[^\]]+))\'([^>]*)>@i', $text, $m2 ) ) {
				foreach ( $m2[4] as $matched_path ) {
					$paths[ $matched_path ] = true;
				}
			}
		}

		$requests = [];
		foreach ( array_keys( $paths ) as $dir_path ) {
			if ( $this->is_content_directory_path( $dir_path ) ) {
				continue;
			}
			list( , $dir_path )             = $this->extract_lang_from_path( $sitepress_settings, $default_language, $dir_path );
			list( $request, $req_uri, , , ) = $this->shape_link_request( $dir_path );
			if ( ! $request || $blacklist_requests->is_blacklisted( $request ) ) {
				continue;
			}
			$requests[] = [ $request, $req_uri ];
		}

		$this->prefetch_verbose_page_paths( $rewrite, $requests );

		$page_names = [];
		$ids        = [];
		$term_slugs = [];
		foreach ( $requests as list( $request, $req_uri ) ) {
			list( $vars, ) = $this->match_rewrite_rules( $rewrite, $request, $req_uri );

			if ( ! empty( $vars['p'] ) ) {
				$ids[ (int) $vars['p'] ] = true;
			} elseif ( isset( $vars['pagename'] ) ) {
				$page_names[ (string) $vars['pagename'] ] = true;
			} elseif ( isset( $vars['name'] ) ) {
				$page_names[ (string) $vars['name'] ] = true;
			} elseif ( isset( $vars['category_name'] ) ) {
				if ( is_string( $vars['category_name'] ) && false === strpos( $vars['category_name'], '/' ) ) {
					$term_slugs[ $vars['category_name'] ] = true;
				}
			} else {
				foreach ( $this->custom_post_query_vars as $query_var ) {
					if ( isset( $vars[ $query_var ] ) ) {
						$page_names[ (string) $vars[ $query_var ] ] = true;
						break;
					}
				}
			}
		}

		if ( $ids ) {
			$rows = $wpdb->get_results( 'SELECT ID, post_type, post_name FROM ' . $wpdb->posts . ' WHERE ID IN (' . wpml_prepare_in( array_keys( $ids ), '%d' ) . ')' );
			foreach ( $rows as $row ) {
				$this->prefetched_ids[ (int) $row->ID ] = [ $row->post_type, $row->post_name ];
				$page_names[ (string) $row->post_name ] = true;
			}
		}

		$parts_union   = [];
		$parts_by_name = [];
		foreach ( array_keys( $page_names ) as $name ) {
			$norm  = rawurlencode( urldecode( (string) $name ) );
			$norm  = str_replace( [ '%2F', '%20' ], [ '/', ' ' ], $norm );
			$parts = array_map( 'sanitize_title_for_query', explode( '/', trim( $norm, '/' ) ) );
			if ( ! $parts || '' === $parts[0] ) {
				continue;
			}
			$parts_by_name[ $name ] = $parts;
			foreach ( $parts as $part ) {
				$parts_union[ $part ] = true;
				if ( '' !== $part && ! isset( $parts_by_name[ $part ] ) ) {
					$parts_by_name[ $part ] = [ $part ];
				}
			}
		}

		if ( $parts_by_name ) {
			$candidate_types = array_unique( array_merge( [ 'page', 'post' ], array_keys( $this->custom_post_query_vars ) ) );
			$fetch_types = $candidate_types;

			$rows = $wpdb->get_results(
				'SELECT ID, post_name, post_parent, post_type FROM ' . $wpdb->posts
				. ' WHERE post_name IN (' . wpml_prepare_in( array_keys( $parts_union ) ) . ')'
				. ' AND post_type IN (' . wpml_prepare_in( $fetch_types ) . ')',
				OBJECT_K
			);

			$row_languages = [];
			if ( $rows ) {
				$icl = $wpdb->get_results(
					'SELECT element_id, language_code FROM ' . $wpdb->prefix . 'icl_translations'
					. ' WHERE element_id IN (' . wpml_prepare_in( array_map( 'intval', array_keys( $rows ) ), '%d' ) . ") AND element_type LIKE 'post_%'"
				);
				foreach ( $icl as $icl_row ) {
					$row_languages[ (int) $icl_row->element_id ][ $icl_row->language_code ] = true;
				}
			}

			$translated_type = [];
			foreach ( $candidate_types as $type ) {
				$translated_type[ $type ] = $sitepress->is_translated_post_type( $type );
			}

			$rows_by_name = [];
			foreach ( $rows as $row_id => $row ) {
				$rows_by_name[ $row->post_name ][] = $row_id;
			}

			foreach ( $parts_by_name as $name => $parts ) {
				$leaf_ids = isset( $rows_by_name[ $parts[ count( $parts ) - 1 ] ] ) ? $rows_by_name[ $parts[ count( $parts ) - 1 ] ] : [];
				foreach ( $this->active_languages as $language ) {
					foreach ( $candidate_types as $type ) {
						$found_id = 0;
						foreach ( $leaf_ids as $row_id ) {
							$page = $rows[ $row_id ];
							if ( ! $this->page_row_in_subset( $page, $row_id, $type, $language, $translated_type, $row_languages ) ) {
								continue;
							}
							$count = 0;
							$p     = $page;
							while ( 0 !== (int) $p->post_parent && isset( $rows[ (int) $p->post_parent ] )
								&& $this->page_row_in_subset( $rows[ (int) $p->post_parent ], (int) $p->post_parent, $type, $language, $translated_type, $row_languages )
							) {
								++$count;
								$parent = $rows[ (int) $p->post_parent ];
								if ( ! isset( $parts[ count( $parts ) - 1 - $count ] ) || $parent->post_name !== $parts[ count( $parts ) - 1 - $count ] ) {
									break;
								}
								$p = $parent;
							}

							if ( 0 === (int) $p->post_parent
								&& count( $parts ) === $count + 1
								&& $p->post_name === $parts[ count( $parts ) - 1 - $count ]
							) {
								$found_id = (int) $page->ID;
								break;
							}
						}
						if ( $found_id ) {
							$this->prefetched_pages[ $name . '|' . $language . '|' . $type ] = $found_id;
						} else {
							$this->prefetched_page_misses[ $name . '|' . $language . '|' . $type ] = true;
						}
					}
				}
			}
		}

		if ( $term_slugs ) {
			$rows = $wpdb->get_results( 'SELECT term_id, slug FROM ' . $wpdb->terms . ' WHERE slug IN (' . wpml_prepare_in( array_keys( $term_slugs ) ) . ') ORDER BY term_id' );
			foreach ( $rows as $row ) {
				if ( ! isset( $this->prefetched_terms[ $row->slug ] ) ) {
					$this->prefetched_terms[ $row->slug ] = (int) $row->term_id;
				}
			}
		}

		$prime_ids = array_merge( array_values( $this->prefetched_pages ), array_keys( $this->prefetched_ids ) );
		if ( $prime_ids && function_exists( '_prime_post_caches' ) ) {
			_prime_post_caches( array_values( array_unique( $prime_ids ) ), true, true );
		}
		if ( $this->prefetched_terms && function_exists( '_prime_term_caches' ) ) {
			_prime_term_caches( array_values( $this->prefetched_terms ) );
		}
	}


	private function page_row_in_subset( $row, $row_id, $type, $language, $translated_type, $row_languages ) {
		if ( $row->post_type !== $type ) {
			return false;
		}

		return ! $translated_type[ $type ] || ! empty( $row_languages[ (int) $row_id ][ $language ] );
	}

	private function language_domain_pattern( $test_language, $sitepress_settings, $default_language, $sitepress ) {
		$home_url = (string) $sitepress->language_url( $test_language );

		if ( WPML_LANGUAGE_NEGOTIATION_TYPE_PARAMETER === WPML_URL_Format::of( $sitepress_settings ) ) {
			$home_url = (string) preg_replace( '#\?lang=([a-z-]+)#i', '', $home_url );
		}
		$home_url = str_replace( '?', '\?', $home_url );

		if ( $sitepress_settings['urls']['directory_for_default_language'] && $test_language === $default_language ) {
			$home_url = $this->get_home_url_with_no_lang_directory( $home_url );
		}

		$site_domain    = $this->get_url_without_scheme( $home_url );
		$domain_pattern = WPML_Same_Site_Url_Normalizer::get_domain_regex_pattern( $site_domain );

		return [ $site_domain, $domain_pattern ];
	}

	private function shape_link_request( $dir_path ) {
		global $wp_rewrite;

		if ( ! $this->batching_disabled() && isset( $this->shape_memo[ $dir_path ] ) ) {
			return $this->shape_memo[ $dir_path ];
		}

		$req_uri        = '/' . $dir_path;
		$req_uri_array  = explode( '?', $req_uri );
		$req_uri        = $req_uri_array[0];
		$req_uri_params = '';
		if ( isset( $req_uri_array[1] ) ) {
			$req_uri_params = $req_uri_array[1];
		}
		$req_uri_array = explode( '#', $req_uri );
		$req_uri       = $req_uri_array[0];

		$anchor_output = isset( $req_uri_array[1] ) ? '#' . $req_uri_array[1] : '';

		if ( null === $this->shape_env || $this->batching_disabled() ) {
			$home_path       = wp_parse_url( get_home_url(), PHP_URL_PATH );
			$home_path_regex = '';

			if ( is_string( $home_path ) && '' !== $home_path ) {
				$home_path = trim( $home_path, '/' );
				$home_path_regex = sprintf( '@^%s(?=/|$)@i', preg_quote( $home_path, '@' ) );
			}

			$pathinfo_raw         = isset( $_SERVER['PATH_INFO'] ) ? $_SERVER['PATH_INFO'] : '';
			list( $pathinfo_raw ) = explode( '?', $pathinfo_raw );
			$pathinfo_raw         = str_replace( '%', '%25', $pathinfo_raw );

			$pathinfo = trim( $pathinfo_raw, '/' );
			if ( '' !== $home_path_regex ) {
				$pathinfo = trim( (string) preg_replace( $home_path_regex, '', $pathinfo ), '/' );
			}

			$shape_env = [
				$home_path,
				$home_path_regex,
				$pathinfo_raw,
				$pathinfo,
				! empty( $pathinfo ) && ! preg_match( '|^.*' . $wp_rewrite->index . '$|', $pathinfo ),
			];
			if ( ! $this->batching_disabled() ) {
				$this->shape_env = $shape_env;
			}
		} else {
			$shape_env = $this->shape_env;
		}
		list( $home_path, $home_path_regex, $pathinfo_raw, $pathinfo, $pathinfo_usable ) = $shape_env;

		$req_uri = rawurldecode( str_replace( $pathinfo_raw, '', $req_uri ) );
		$req_uri = trim( $req_uri, '/' );

		if ( ! empty( $home_path_regex ) ) {
			$req_uri = preg_replace( $home_path_regex, '', $req_uri );
			$req_uri = trim( $req_uri, '/' );
		}

		if ( $pathinfo_usable ) {
			$request = $pathinfo;
		} else {
			if ( $req_uri === $wp_rewrite->index ) {
				$req_uri = '';
			}
			$request = $req_uri;
		}

		$this->shape_memo[ $dir_path ] = [ $request, $req_uri, $req_uri_params, $anchor_output, $home_path ];

		return $this->shape_memo[ $dir_path ];
	}

	private function match_rewrite_rules( $rewrite, $request, $req_uri ) {
		if ( $this->batching_disabled() ) {
			return $this->match_rewrite_rules_uncached( $rewrite, $request, $req_uri );
		}

		$memo_key = $request . "\0" . $req_uri;
		if ( isset( $this->rule_match_memo[ $memo_key ] ) ) {
			return $this->rule_match_memo[ $memo_key ];
		}

		list( $permalink_query_vars, $matched_query ) = $this->match_rewrite_rules_uncached( $rewrite, $request, $req_uri );

		$this->rule_match_memo[ $memo_key ] = [ $permalink_query_vars, $matched_query ];

		return $this->rule_match_memo[ $memo_key ];
	}

	private function match_rewrite_rules_uncached( $rewrite, $request, $req_uri ) {
		global $wp_rewrite;

		$request_match = $request;

		$permalink_query_vars = [];

		if ( null === $this->rule_bucket_index ) {
			$buckets  = [];
			$fallback = [];
			$ordered  = [];
			$i        = 0;
			foreach ( (array) $rewrite as $match => $query ) {
				$ordered[ $i ] = [ $match, $query ];
				if ( preg_match( '@^([a-zA-Z0-9_\-]+)/@', (string) $match, $m ) ) {
					$buckets[ strtolower( $m[1] ) ][] = $i;
				} else {
					$fallback[] = $i;
				}
				$i++;
			}
			$this->rule_bucket_index = [ $buckets, $fallback, $ordered ];
		}
		list( $buckets, $fallback, $ordered ) = $this->rule_bucket_index;

		$use_buckets = ! $this->batching_disabled() && ( '' === $req_uri || $req_uri === $request );

		if ( $use_buckets ) {
			$candidates = $fallback;
			$first_seg  = strtolower( (string) strstr( $request . '/', '/', true ) );
			if ( isset( $buckets[ $first_seg ] ) ) {
				$candidates = array_merge( $candidates, $buckets[ $first_seg ] );
			}
			$decoded = urldecode( $request );
			if ( $decoded !== $request ) {
				$decoded_seg = strtolower( (string) strstr( $decoded . '/', '/', true ) );
				if ( $decoded_seg !== $first_seg && isset( $buckets[ $decoded_seg ] ) ) {
					$candidates = array_merge( $candidates, $buckets[ $decoded_seg ] );
				}
			}
			sort( $candidates );
		} else {
			$candidates = array_keys( $ordered );
		}

		foreach ( $candidates as $rule_position ) {
			list( $match, $query ) = $ordered[ $rule_position ];
			if ( ( ! empty( $req_uri ) ) && ( strpos( $match, $req_uri ) === 0 ) && ( $req_uri !== $request ) ) {
				$request_match = $req_uri . '/' . $request;
			}

			if ( preg_match( "#^$match#", $request_match, $matches ) || preg_match( "#^$match#", urldecode( $request_match ), $matches ) ) {
				if ( ! empty( $wp_rewrite->use_verbose_page_rules )
					&& preg_match( '/pagename=\$matches\[([0-9]+)\]/', $query, $varmatch )
				) {
					$page_path = (string) $matches[ $varmatch[1] ];

					if ( is_array( $this->verbose_page_probe ) ) {
						$this->verbose_page_probe[ $page_path ] = true;
						continue;
					}

					if ( ! $this->verbose_page_exists( $page_path ) ) {
						continue;
					}
				}


				$query = preg_replace( '!^.+\?!', '', $query );

				$query = addslashes( WP_MatchesMapRegex::apply( $query, $matches ) );

				parse_str( $query, $permalink_query_vars );

				return [ $permalink_query_vars, $query ];
			}
		}

		return [ $permalink_query_vars, null ];
	}

	private function verbose_page_exists( $page_path ) {
		if ( ! isset( $this->verbose_page_paths[ $page_path ] ) ) {
			$page = get_page_by_path( $page_path );

			$this->verbose_page_paths[ $page_path ] = $page && $this->verbose_page_status_counts( $page->post_status );
		}

		return $this->verbose_page_paths[ $page_path ];
	}

	private function verbose_page_status_counts( $post_status ) {
		$status = get_post_status_object( $post_status );

		if ( ! $status ) {
			return true;
		}

		return $status->public || $status->protected || $status->private || ! $status->exclude_from_search;
	}

	private function prefetch_verbose_page_paths( $rewrite, array $requests ) {
		global $wpdb, $wp_rewrite;

		if ( ! $requests || empty( $wp_rewrite->use_verbose_page_rules ) ) {
			return;
		}

		$this->verbose_page_probe = [];
		foreach ( $requests as list( $request, $req_uri ) ) {
			$this->match_rewrite_rules_uncached( $rewrite, $request, $req_uri );
		}
		$page_paths               = array_keys( $this->verbose_page_probe );
		$this->verbose_page_probe = null;

		$parts_by_path = [];
		$parts_union   = [];
		foreach ( $page_paths as $page_path ) {
			$page_path = (string) $page_path;
			if ( isset( $this->verbose_page_paths[ $page_path ] ) ) {
				continue;
			}
			$norm  = rawurlencode( urldecode( $page_path ) );
			$norm  = str_replace( [ '%2F', '%20' ], [ '/', ' ' ], $norm );
			$parts = array_map( 'sanitize_title_for_query', explode( '/', trim( $norm, '/' ) ) );

			$parts_by_path[ $page_path ] = array_reverse( $parts );
			foreach ( $parts as $part ) {
				$parts_union[ $part ] = true;
			}
		}

		if ( ! $parts_by_path ) {
			return;
		}

		$rows = $wpdb->get_results(
			'SELECT ID, post_name, post_parent, post_type, post_status FROM ' . $wpdb->posts
			. ' WHERE post_name IN (' . wpml_prepare_in( array_map( 'strval', array_keys( $parts_union ) ) ) . ')'
			. " AND post_type IN ('page', 'attachment')",
			OBJECT_K
		);

		$rows_by_name = [];
		foreach ( (array) $rows as $row_id => $row ) {
			$rows_by_name[ $row->post_name ][] = $row_id;
		}

		foreach ( $parts_by_path as $page_path => $revparts ) {
			$leaf_ids = isset( $rows_by_name[ $revparts[0] ] ) ? $rows_by_name[ $revparts[0] ] : [];
			$answers  = [];
			foreach ( $leaf_ids as $row_id ) {
				$count = 0;
				$p     = $rows[ $row_id ];
				while ( 0 !== (int) $p->post_parent && isset( $rows[ (int) $p->post_parent ] ) ) {
					++$count;
					$parent = $rows[ (int) $p->post_parent ];
					if ( ! isset( $revparts[ $count ] ) || $parent->post_name !== $revparts[ $count ] ) {
						break;
					}
					$p = $parent;
				}

				if ( 0 === (int) $p->post_parent
					&& count( $revparts ) === $count + 1
					&& $p->post_name === $revparts[ $count ]
				) {
					$answers[ $this->verbose_page_status_counts( $rows[ $row_id ]->post_status ) ? 1 : 0 ] = true;
				}
			}

			if ( ! $answers ) {
				$this->verbose_page_paths[ $page_path ] = false;
			} elseif ( 1 === count( $answers ) ) {
				$this->verbose_page_paths[ $page_path ] = isset( $answers[1] );
			}
		}
	}

	private function with_page_number( $req_uri_params, array $permalink_query_vars ) {
		$page_number = isset( $permalink_query_vars['page'] ) ? (int) trim( (string) $permalink_query_vars['page'], '/' ) : 0;

		if ( $page_number < 2 ) {
			return $req_uri_params;
		}

		return 'page=' . $page_number . ( '' !== (string) $req_uri_params ? '&' . $req_uri_params : '' );
	}

	private function get_home_url_with_no_lang_directory( $url = null ) {
		global $sitepress, $sitepress_settings;
		$sitepress_settings = $sitepress->get_settings();

		if ( $url ) {
			$home_url = rtrim( $url, '/' );
		} else {
			$home_url = rtrim( get_home_url(), '/' );
		}

		if ( WPML_LANGUAGE_NEGOTIATION_TYPE_DIRECTORY === WPML_URL_Format::of( $sitepress_settings ) ) {
			$exp  = explode( '/', $home_url );
			$lang = end( $exp );

			if ( $this->does_lang_exist( $lang ) ) {
				$home_url = substr( $home_url, 0, strlen( $home_url ) - strlen( $lang ) );
			}
		}

		return $home_url;
	}

	private function does_lang_exist( $lang ) {
		return in_array( $lang, $this->active_languages, true )
			|| in_array( $lang, $this->url_language_codes, true );
	}

	private function get_url_language_codes( array $codes ) {
		$codes = array_map( 'strval', $codes );
		if ( [] === $codes ) {
			return $codes;
		}

		$map = apply_filters( 'wpml_language_codes_map', array_combine( $codes, $codes ) );

		return is_array( $map )
			? array_values( array_unique( array_merge( $codes, array_map( 'strval', $map ) ) ) )
			: $codes;
	}

	private function is_content_directory_path( $path ) {
		$wp_content_path = wp_parse_url( WP_CONTENT_URL, PHP_URL_PATH );

		return $wp_content_path && 0 === strpos( '/' . $path, $wp_content_path );
	}

	public function _get_ids_and_post_types( $name ) {
		global $wpdb;
		static $cache = [];

		$name = rawurlencode( $name );
		if ( ! isset( $cache[ $name ] ) ) {
			$cache[ $name ] = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_type FROM {$wpdb->posts} WHERE post_name LIKE %s AND post_type IN('post','page')", $name . '%' ) );
		}

		return $cache[ $name ];
	}

	private function initialize_rewrite( $current_language, $default_language, $sitepress, $language_scope = null ) {
		global $wp_rewrite;

		$key = is_array( $language_scope )
			? md5(
				(string) wp_json_encode(
					[ $current_language, $default_language, array_values( $language_scope ) ]
				)
			)
			: $current_language . $default_language;
		$found    = false;
		$rewrite  = WPML_Non_Persistent_Cache::get( $key, __CLASS__, $found );
		if ( $found ) {
			return $rewrite;
		}

		if ( ! isset( $wp_rewrite ) ) {
			require_once ABSPATH . WPINC . '/rewrite.php';
			$wp_rewrite = new WP_Rewrite();
		}

		$is_scoped         = is_array( $language_scope );
		$previous_language = $sitepress->get_current_language();
		if ( $is_scoped && $previous_language !== $current_language ) {
			$sitepress->switch_lang( $current_language );
		}

		add_filter( 'pre_update_option_rewrite_rules', [ $this, 'keep_stored_rewrite_rules' ], 10, 2 );

		try {
			if ( $current_language === $default_language ) {
				$rewrite = $wp_rewrite->wp_rewrite_rules();
			} else {
				$rewrite_filter_priority = $is_scoped
					? has_filter( 'option_rewrite_rules', [ $sitepress, 'rewrite_rules_filter' ] )
					: false;

				remove_filter( 'option_rewrite_rules', [ $sitepress, 'rewrite_rules_filter' ] );
				add_filter( 'wpml_st_disable_rewrite_rules', '__return_true' );

				try {
					$rewrite = $wp_rewrite->wp_rewrite_rules();
				} finally {
					remove_filter( 'wpml_st_disable_rewrite_rules', '__return_true' );

					if ( $is_scoped && false !== $rewrite_filter_priority ) {
						add_filter(
							'option_rewrite_rules',
							[ $sitepress, 'rewrite_rules_filter' ],
							$rewrite_filter_priority
						);
					}
				}
			}

			$rewrite = $this->all_rewrite_rules( $rewrite, $language_scope );
		} finally {
			remove_filter( 'pre_update_option_rewrite_rules', [ $this, 'keep_stored_rewrite_rules' ], 10 );

			if ( $is_scoped && $previous_language !== $current_language ) {
				$sitepress->switch_lang();
			}
		}

		WPML_Non_Persistent_Cache::set( $key, $rewrite, __CLASS__ );

		return $rewrite;
	}

	public function keep_stored_rewrite_rules( $value, $old_value ) {
		return $old_value;
	}

	public function all_rewrite_rules( $rewrite, $language_scope = null ) {
		global $sitepress;

		if ( ! class_exists( 'WPML\ST\SlugTranslation\Hooks\Hooks' ) ) {
			return $rewrite;
		}

		$active_languages = $sitepress->get_active_languages();
		if ( is_array( $language_scope ) ) {
			$active_languages = array_intersect_key(
				$active_languages,
				array_fill_keys( $language_scope, true )
			);
		}
		$current_language = $sitepress->get_current_language();
		$default_language = $sitepress->get_default_language();

		$cache_keys   = [ $current_language, $default_language ];
		$cache_keys[] = md5( (string) wp_json_encode( $active_languages ) );
		$cache_keys[] = md5( (string) wp_json_encode( $rewrite ) );
		$cache_key    = implode( ':', $cache_keys );
		$cache_group  = 'all_rewrite_rules';
		$cache_found  = false;

		$final_rules = WPML_Non_Persistent_Cache::get( $cache_key, $cache_group, $cache_found );

		if ( $cache_found ) {
			return $final_rules;
		}

		$final_rules = $rewrite;

		$built_in_default_language = $current_language === $default_language;

		foreach ( $active_languages as $next_language ) {

			if ( $built_in_default_language && $next_language['code'] === $default_language ) {
				continue;
			}

			$sitepress->switch_lang( $next_language['code'] );

			try {
				$translated_rules = ( new \WPML\ST\SlugTranslation\Hooks\HooksFactory() )->create()->filter( $final_rules );

				if ( is_array( $translated_rules ) && is_array( $final_rules ) ) {
					$new_rules = array_diff_assoc( $translated_rules, $final_rules );

					$final_rules = array_merge( $new_rules, $final_rules );
				}
			} finally {
				$sitepress->switch_lang();
			}
		}

		WPML_Non_Persistent_Cache::set( $cache_key, $final_rules, $cache_group );

		return $final_rules;
	}

	private function get_regex_replacement(
		$def_url,
		$type,
		$type_id,
		$lang_negotiation,
		$lang,
		$dir_path,
		$site_domain,
		$url_parts,
		$req_uri_params,
		$anchor_output
	) {

		$type_id = $this->maybeStripParentTerm( $type_id );

		if ( WPML_LANGUAGE_NEGOTIATION_TYPE_DIRECTORY === (int) $lang_negotiation && $lang ) {
			$langprefix = '/' . $lang;
		} else {
			$langprefix = '';
		}
		$domain_pattern = WPML_Same_Site_Url_Normalizer::get_domain_regex_pattern( $site_domain );
		$perm_url  = '(https?://' . $domain_pattern . ')?';
		$perm_url .= preg_quote( $langprefix, '@' ) . '/' . preg_quote( $dir_path, '@' );
		$regk      = '@href=[\'"](' . $perm_url . ')[\'"]@i';
		$target    = '/' . ltrim( $url_parts['path'], '/' ) . '?' . $type . '=' . $type_id;
		if ( '' !== $req_uri_params ) {
			$target .= '&' . $req_uri_params;
		}
		$target .= $anchor_output;

		$def_url[ $regk ] = 'href="' . $target . '"';

		return $this->add_block_attribute_replacement( $def_url, $langprefix, $dir_path, $domain_pattern, $target );
	}

	private function add_block_attribute_replacement( $def_url, $langprefix, $dir_path, $domain_pattern, $target_url ) {
		return array_merge(
			$def_url,
			AttributeUrls::stickyLinkRule( $domain_pattern, $langprefix, $dir_path, $target_url )
		);
	}

	private function maybeStripParentTerm( $typeId ) {
		return Lst::nth( -1, Str::split( '/', $typeId ) );
	}

	private function get_archive_url_in_language( $permalink_query_vars, $language ) {
		global $sitepress;

		if ( ! is_array( $permalink_query_vars ) || ! $permalink_query_vars ) {
			return false;
		}

		$switch_lang = new WPML_Temporary_Switch_Language( $sitepress, $language );

		try {
			$url = $this->get_archive_url( $permalink_query_vars );
		} finally {
			$switch_lang->restore_lang();
		}

		if ( ! is_string( $url ) || '' === $url ) {
			return false;
		}

		$url = $sitepress->convert_url( $url, $language );

		return is_string( $url ) && '' !== $url ? $url : false;
	}

	private function get_archive_url( $vars ) {
		$url = false;

		if ( ! empty( $vars['author_name'] ) && is_string( $vars['author_name'] ) ) {
			$author = get_user_by( 'slug', $vars['author_name'] );
			$url    = $author ? get_author_posts_url( $author->ID, $author->user_nicename ) : false;
		} elseif ( ! empty( $vars['year'] ) ) {
			if ( ! empty( $vars['monthnum'] ) && ! empty( $vars['day'] ) ) {
				$url = get_day_link( $vars['year'], $vars['monthnum'], $vars['day'] );
			} elseif ( ! empty( $vars['monthnum'] ) ) {
				$url = get_month_link( $vars['year'], $vars['monthnum'] );
			} else {
				$url = get_year_link( $vars['year'] );
			}
		} elseif ( ! empty( $vars['post_type'] ) ) {
			$post_type = is_array( $vars['post_type'] ) ? reset( $vars['post_type'] ) : $vars['post_type'];
			$url       = is_string( $post_type ) ? get_post_type_archive_link( $post_type ) : false;
		}

		if ( ! is_string( $url ) || '' === $url ) {
			return false;
		}

		if ( ! empty( $vars['paged'] ) ) {
			if ( false !== strpos( $url, '?' ) ) {
				return false;
			}

			global $wp_rewrite;

			if ( ! isset( $wp_rewrite->pagination_base ) ) {
				return false;
			}

			$url = user_trailingslashit(
				trailingslashit( $url ) . $wp_rewrite->pagination_base . '/' . (int) $vars['paged'],
				'paged'
			);
		}

		return $url;
	}

	private function get_regex_replacement_url(
		$def_url,
		$target_url,
		$lang_negotiation,
		$lang,
		$dir_path,
		$site_domain,
		$req_uri_params,
		$anchor_output
	) {
		if ( WPML_LANGUAGE_NEGOTIATION_TYPE_DIRECTORY === (int) $lang_negotiation && $lang ) {
			$langprefix = '/' . $lang;
		} else {
			$langprefix = '';
		}
		$domain_pattern = WPML_Same_Site_Url_Normalizer::get_domain_regex_pattern( $site_domain );
		$perm_url       = '(https?://' . $domain_pattern . ')?';
		$perm_url      .= preg_quote( $langprefix, '@' ) . '/' . preg_quote( $dir_path, '@' );
		$regk           = '@href=[\'"](' . $perm_url . ')[\'"]@i';

		$target = $target_url;
		if ( '' !== $req_uri_params ) {
			$target .= ( false === strpos( $target_url, '?' ) ? '?' : '&' ) . $req_uri_params;
		}
		$target .= $anchor_output;

		$def_url[ $regk ] = 'href="' . $target . '"';

		return $this->add_block_attribute_replacement( $def_url, $langprefix, $dir_path, $domain_pattern, $target );
	}

	private function get_regex_replacement_offline(
		$def_url,
		$offsite_url,
		$lang_negotiation,
		$lang,
		$dir_path,
		$site_domain,
		$anchor_output
	) {
		if ( WPML_LANGUAGE_NEGOTIATION_TYPE_DIRECTORY === (int) $lang_negotiation && $lang ) {
			$langprefix = '/' . $lang;
		} else {
			$langprefix = '';
		}
		$domain_pattern = WPML_Same_Site_Url_Normalizer::get_domain_regex_pattern( $site_domain );
		$perm_url  = '(https?://' . $domain_pattern . ')?';
		$perm_url .= preg_quote( $langprefix, '@' ) . '/' . preg_quote( $dir_path, '@' );
		$regk      = '@href=["\'](' . $perm_url . ')["\']@i';
		$target    = $offsite_url . $anchor_output;

		$def_url[ $regk ] = 'href="' . $target . '"';

		return $this->add_block_attribute_replacement( $def_url, $langprefix, $dir_path, $domain_pattern, $target );
	}

	private function extract_lang_from_path( $sitepress_settings, $default_language, $dir_path ) {
		$lang = false;

		if ( WPML_LANGUAGE_NEGOTIATION_TYPE_DIRECTORY === WPML_URL_Format::of( $sitepress_settings ) ) {
			$exp  = explode( '/', $dir_path, 2 );
			$lang = $exp[0];
			if ( $this->does_lang_exist( $lang ) ) {
				$dir_path = isset( $exp[1] ) ? $exp[1] : '';
			} else {
				$lang = false;
			}
		}

		return [ $lang, $dir_path ];
	}


	public function process_string( $st_id ) {
		global $wpdb;
		if ( $st_id ) {

			$table = $wpdb->prefix . 'icl_string_translations';

			$data         = $wpdb->get_row( $wpdb->prepare( "SELECT value, string_id, language FROM {$wpdb->prefix}icl_string_translations WHERE id=%d", $st_id ) );
			$string_value = $data->value;
			$string_type  = $wpdb->get_var( $wpdb->prepare( "SELECT type FROM {$wpdb->prefix}icl_strings WHERE id=%d", $data->string_id ) );

			if ( 'LINK' === $string_type ) {
				$string_value_up = $this->convert_url( $string_value, $data->language );
			} else {
				$string_value_up = $this->convert_text( $string_value );
			}

			if ( $string_value_up !== $string_value ) {
				$wpdb->update(
					$table,
					[
						'value'  => $string_value_up,
						'status' => ICL_STRING_TRANSLATION_COMPLETE,
					],
					[ 'id' => $st_id ]
				);
			}
		}
	}

	public function process_post( $post_id ) {
		global $wpdb, $sitepress;

		delete_post_meta( $post_id, '_alp_broken_links' );

		$post = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->posts} WHERE ID = %s", $post_id ) );

		$this_post_language = $sitepress->get_language_for_element( $post_id, 'post_' . $post->post_type );
		$sitepress->switch_lang( $this_post_language );

		$alp_broken_links = [];

		try {
			$post_content = $this->convert_text( $post->post_content, true, null, $alp_broken_links );
		} finally {
			$sitepress->switch_lang();
		}

		if ( $post_content !== $post->post_content ) {
			$updated = $wpdb->update( $wpdb->posts, [ 'post_content' => $post_content ], [ 'ID' => $post_id ] );

			if ( false !== $updated ) {
				clean_post_cache( $post_id );
			}
		}

		update_post_meta( $post_id, '_alp_processed', time() );

		if ( $alp_broken_links ) {
			update_post_meta( $post_id, '_alp_broken_links', $alp_broken_links );
		}
	}

	public function convert_text( $text, $ignore_blocks = true, ?CollectorInterface $collector = null, &$alp_broken_links = null ) {
		$alp_broken_links = [];

		return $this->_process_generic_text( $text, $alp_broken_links, $ignore_blocks, $collector );
	}

	public function convert_text_for_language( $text, $source_language, ?CollectorInterface $collector = null ) {
		$alp_broken_links = [];

		return $this->_process_generic_text(
			$text,
			$alp_broken_links,
			true,
			$collector,
			[ $source_language ]
		);
	}

	public function convert_url( $url, $lang = null ) {
		global $sitepress;

		if ( $this->is_home( $url ) ) {
			$absolute_url = $sitepress->convert_url( $url, $lang );
		} else {

			$html         = '<a href="' . $url . '">removeit</a>';
			$html         = $this->convert_text( $html );
			$absolute_url = str_replace( [ '<a href="', '">removeit</a>' ], [ '', '' ], $html );
		}

		return $absolute_url;
	}

	public function is_home( $url ) {
		if ( preg_match( '/^https?:\/\//', $url ) !== 1 ) {
			return false;
		}

		$exact_match = $this->get_url_without_scheme( get_home_url() ) === $this->get_url_without_scheme( $url );
		if ( $exact_match ) {
			return true;
		}
		return WPML_Same_Site_Url_Normalizer::is_home_url( $url );
	}

	private function is_pagination_in_post( $url, $post_name ) {
		$is_pagination_url_in_post = false !== mb_strpos( $url, $post_name . '/page/' );

		return apply_filters( 'wpml_is_pagination_url_in_post', $is_pagination_url_in_post, $url, $post_name );
	}

	private function maybe_extract_page_name( $page_name, $sitepress_settings, $wp_rewrite ) {
		if ( strpos( $page_name, '/' ) !== false ) {
			$page_name_elements = explode( '/', $page_name );

			$permalink_structure          = trim( $wp_rewrite->permalink_structure, '/' );
			$permalink_structure_elements = explode( '/', $permalink_structure );

			if ( count( $page_name_elements ) !== count( $permalink_structure_elements ) ) {
				return apply_filters( 'wpml_maybe_extract_page_name', $page_name, $sitepress_settings, $wp_rewrite );
			}

			foreach ( $permalink_structure_elements as $key => $element ) {
				if ( '%postname%' === $element && isset( $page_name_elements[ $key ] ) ) {
					$page_name = $page_name_elements[ $key ];
					break;
				}

				if ( '%post_id%' === $element && isset( $page_name_elements[ $key ] ) ) {
					$post_type_and_name = $this->get_post_type_and_name_from_post_id( $page_name_elements[ $key ] );
					if ( $post_type_and_name ) {
						list( $post_type, $post_name ) = $post_type_and_name;
						$page_name                     = $post_name;
					}
					break;
				}
			}
		}

		return apply_filters( 'wpml_maybe_extract_page_name', $page_name, $sitepress_settings, $wp_rewrite );
	}

	private function get_page_by_path_with_original_fallback(
		WPML_Get_Page_By_Path $page_resolver,
		$page_name,
		$original_page_name,
		$language
	) {
		$page_by_path = $this->page_by_path_prefetched( $page_resolver, $page_name, $language );

		if ( ! $page_by_path && $page_name !== $original_page_name ) {
			$page_by_path = $this->page_by_path_prefetched( $page_resolver, $original_page_name, $language );
		}

		return $page_by_path;
	}

	private function find_custom_post_type_by_slug( $pagename, $language ) {
		global $wpdb, $sitepress;

		$slug = basename( $pagename );

		if ( ! $slug ) {
			return null;
		}

		$custom_post_types = array_values(
			array_filter( array_keys( $this->custom_post_query_vars ), [ $this, 'is_post_type_reachable_by_slug' ] )
		);

		if ( ! $custom_post_types ) {
			return null;
		}

		$translated_custom_post_types     = [];
		$not_translated_custom_post_types = [];

		foreach ( $custom_post_types as $custom_post_type ) {
			if ( $sitepress->is_translated_post_type( $custom_post_type ) ) {
				$translated_custom_post_types[] = $custom_post_type;
			} else {
				$not_translated_custom_post_types[] = $custom_post_type;
			}
		}

		$cache_key   = md5(
			(string) wp_json_encode(
				[
					$slug,
					$language,
					$translated_custom_post_types,
					$not_translated_custom_post_types,
				]
			)
		);
		$cache_found = false;
		$custom_post = WPML_Non_Persistent_Cache::get( $cache_key, __METHOD__, $cache_found );

		if ( $cache_found ) {
			return $custom_post;
		}

		$where_conditions = [];
		$query_args       = [ $slug ];

		if ( $not_translated_custom_post_types ) {
			$where_conditions[] = 'p.post_type IN (' . wpml_prepare_in( $not_translated_custom_post_types ) . ')';
		}

		if ( $translated_custom_post_types ) {
			$where_conditions[] = 'translations.language_code = %s';
			$query_args[]       = $language;
		}

		if ( ! $where_conditions ) {
			WPML_Non_Persistent_Cache::set( $cache_key, null, __METHOD__ );

			return null;
		}

		$custom_post = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT p.ID, p.post_type, p.post_name FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->prefix}icl_translations translations
					ON translations.element_id = p.ID
					AND translations.element_type IN (" . wpml_prepare_in( wpml_post_element_types( $custom_post_types ) ) . ")
				WHERE p.post_name = %s
				AND p.post_type IN (" . wpml_prepare_in( $custom_post_types ) . ')
					AND (' . implode( ' OR ', $where_conditions ) . ')
					LIMIT 1',
				$query_args
			)
		);

		WPML_Non_Persistent_Cache::set( $cache_key, $custom_post, __METHOD__ );

		return $custom_post;
	}

	private function is_post_type_reachable_by_slug( $post_type ) {
		$post_type_object = get_post_type_object( $post_type );

		if ( ! $post_type_object || empty( $post_type_object->publicly_queryable ) ) {
			return false;
		}

		return ! empty( $post_type_object->_builtin ) || false !== $post_type_object->rewrite;
	}

	private function get_post_type_and_name_from_post_id( $post_id ) {
		global $wpdb;

		if ( isset( $this->prefetched_ids[ (int) $post_id ] ) ) {
			return $this->prefetched_ids[ (int) $post_id ];
		}

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT post_type, post_name FROM {$wpdb->posts} WHERE id=%d", $post_id ),
			ARRAY_N
		);
	}

	private function get_url_without_scheme( string $url ): string {
		return rtrim( preg_replace( '/^https?:\/\//', '', $url ), '/' );
	}
}
