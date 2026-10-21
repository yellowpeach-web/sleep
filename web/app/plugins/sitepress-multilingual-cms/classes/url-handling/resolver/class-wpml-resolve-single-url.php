<?php

use WPML\UrlHandling\Resolver\EndpointSlugs;

class WPML_Resolve_Single_Url {

	private $sitepress;

	private $url_converter;

	private $translate_link_targets;

	private $persistent_cache;

	private $inline_resolver;

	private $endpoint_slugs;

	private $cache = [];

	public function __construct(
		SitePress $sitepress,
		WPML_URL_Converter $url_converter,
		WPML_Translate_Link_Targets $translate_link_targets,
		?WPML_Single_Url_Resolution_Cache $persistent_cache = null,
		?EndpointSlugs $endpoint_slugs = null
	) {
		$this->sitepress              = $sitepress;
		$this->url_converter          = $url_converter;
		$this->translate_link_targets = $translate_link_targets;
		$this->persistent_cache       = $persistent_cache;
		$this->endpoint_slugs         = $endpoint_slugs ?: new EndpointSlugs( $sitepress );
	}

	public function set_inline_resolver( WPML_Single_Url_Inline_Resolver $inline_resolver ) {
		$this->inline_resolver = $inline_resolver;
	}

	public function resolve( $url, $target_language, $source_language_hint = null ) {
		$source_language  = $this->resolve_source_language( $url, $source_language_hint );
		$result           = $this->default_result( $url, $source_language );
		$active_languages = array_keys( $this->sitepress->get_active_languages() );

		if (
			! is_string( $url )
			|| '' === $url
			|| ! is_string( $target_language )
			|| '' === $target_language
			|| ! in_array( $target_language, $active_languages, true )
			|| ! $source_language
		) {
			return $result;
		}

		$cache_key = md5( get_current_blog_id() . "\0" . $source_language . "\0" . $target_language . "\0" . $url );
		if ( array_key_exists( $cache_key, $this->cache ) ) {
			return $this->for_current_reader( $this->cache[ $cache_key ], $url, $target_language );
		}

		if ( ! $this->is_internal_url( $url ) ) {
			$this->cache[ $cache_key ] = $result;
			return $result;
		}

		if ( $this->is_content_directory_url( $url ) ) {
			$this->cache[ $cache_key ] = $result;
			return $result;
		}

		$should_resolve = apply_filters(
			'wpml_should_resolve_single_url',
			true,
			$url,
			$target_language,
			$source_language
		);

		if ( false === $should_resolve ) {
			$this->cache[ $cache_key ] = $result;
			return $result;
		}

		$should_cache = apply_filters(
			'wpml_should_cache_single_url_resolution',
			true,
			$url,
			$target_language,
			$source_language
		);

		if ( strlen( $url ) > 4096 ) {
			$this->cache[ $cache_key ] = $result;
			return $result;
		}

		if (
			$this->persistent_cache
			&& false !== $should_cache
		) {
			$result = $this->persistent_cache->get_or_defer(
				$url,
				$source_language,
				$target_language
			);

			if (
				$this->inline_resolver
				&& isset( $result['resolution_state'] )
				&& WPML_Single_Url_Cache_Entry::PUBLIC_DEFERRED === $result['resolution_state']
			) {
				$inline_result = $this->inline_resolver->try_resolve(
					$url,
					$source_language,
					$target_language
				);
				if ( $inline_result ) {
					$result = $inline_result;
				}
			}

			$this->cache[ $cache_key ] = $result;
			return $this->for_current_reader( $result, $url, $target_language );
		}

		$result                    = $this->resolve_uncached( $url, $target_language, $source_language );
		$this->cache[ $cache_key ] = $this->public_result( $result, $url );

		return $this->for_current_reader( $this->cache[ $cache_key ], $url, $target_language );
	}

	public function resolve_uncached_for_cache( $url, $target_language, $source_language ) {
		return $this->resolve_uncached( $url, $target_language, $source_language );
	}

	private function resolve_uncached( $url, $target_language, $source_language ) {
		$result = $this->default_result( $url, $source_language );

		$front_page_id = $this->get_front_page_id( $url, $source_language );
		if ( $front_page_id ) {
			$result['source_post_id']      = $front_page_id;
			$result['translated_post_id']  = $this->translate_post_id( $front_page_id, $target_language );
			$result['url']                 = $source_language === $target_language
				? $url
				: $this->url_converter->convert_url( $url, $target_language );
			$result['_cache_state']        = $result['translated_post_id']
				? WPML_Single_Url_Cache_Entry::STATE_POSITIVE
				: WPML_Single_Url_Cache_Entry::STATE_MISSING_TRANSLATION;
			$result['_source_object_kind'] = 'post';
			$result['_source_object_id']   = $front_page_id;
			$result['_translation_trid']   = $this->get_post_trid( $front_page_id );
			return $result;
		}

		$collector     = new WPML_Single_Url_Collector();
		$converted_url = $this->convert_scoped( $url, $source_language, $target_language, $collector );
		$source_id     = $collector->get_post_id();
		$resolved_term = $collector->get_term_id();

		if ( $source_id && $this->ends_in_page_number( $url ) ) {
			$source_id = $this->page_number_owner( $url, $source_id, $source_language, $target_language );
			if ( ! $source_id ) {
				$result['_cache_state'] = WPML_Single_Url_Cache_Entry::STATE_NEGATIVE;
				return $result;
			}
		}
		if ( ! $source_id && ! $resolved_term && $this->ends_in_page_number( $url ) ) {
			$source_id = $this->paged_post_id( $url, $source_language, $target_language );
		}

		if ( ! $source_id && ! $resolved_term ) {
			$source_id = $this->get_query_post_id( $url );
		}
		if ( ! $source_id && ! $resolved_term ) {
			$source_id = $this->endpoint_owner( $url, $source_language, $target_language );
		}
		if ( ! $source_id && ! $resolved_term && ! $this->ends_in_page_number( $url ) ) {
			$source_id = $this->resolve_native_post_id( $url, $source_language );
		}

		if ( $source_id ) {
			$translated_id = $this->translate_post_id( $source_id, $target_language );

			$result['source_post_id']     = $source_id;
			$result['translated_post_id'] = $translated_id;

			if (
				$translated_id
				&& (
					(int) $translated_id !== (int) $source_id
					|| $this->is_plain_link_to( $url, (int) $source_id )
				)
			) {
				$translated_url = $this->translated_post_url(
					$url,
					$source_id,
					$translated_id,
					$source_language,
					$target_language
				);

				if ( null === $translated_url ) {
					$result['source_post_id']     = null;
					$result['translated_post_id'] = null;
					$result['_cache_state']       = WPML_Single_Url_Cache_Entry::STATE_NEGATIVE;
					return $result;
				}
				if ( '' !== $translated_url ) {
					$result['url'] = $this->in_input_form( $translated_url, $url );
				}
			}

			$result['_cache_state']        = $translated_id
				? WPML_Single_Url_Cache_Entry::STATE_POSITIVE
				: WPML_Single_Url_Cache_Entry::STATE_MISSING_TRANSLATION;
			$result['_source_object_kind'] = 'post';
			$result['_source_object_id']   = $source_id;
			$result['_translation_trid']   = $this->get_post_trid( $source_id );
		} elseif ( $resolved_term ) {
			$term          = get_term( $resolved_term );
			$translated_id = $term instanceof WP_Term
				? $this->translate_term_id( $resolved_term, $term->taxonomy, $target_language )
				: null;

			$term_url = $translated_id && (int) $translated_id === (int) $resolved_term
				? null
				: $this->resolved_term_url(
					$converted_url,
					$url,
					$term,
					$translated_id,
					$target_language
				);
			if ( is_string( $term_url ) && '' !== $term_url ) {
				$result['url'] = $this->in_input_form( $term_url, $url );
			}
			$result['_cache_state']          = $translated_id
				? WPML_Single_Url_Cache_Entry::STATE_POSITIVE
				: WPML_Single_Url_Cache_Entry::STATE_MISSING_TRANSLATION;
			$result['_source_object_kind']   = 'term';
			$result['_source_object_id']     = $resolved_term;
			$result['_translated_object_id'] = $translated_id;
			$result['_translation_trid']     = $term instanceof WP_Term
				? $this->get_term_trid( $term )
				: null;
		} elseif ( is_string( $converted_url ) && '' !== $converted_url ) {
			$result['url']          = $this->in_input_form( $converted_url, $url );
			$result['_cache_state'] = $converted_url !== $url
				? WPML_Single_Url_Cache_Entry::STATE_ROUTE
				: WPML_Single_Url_Cache_Entry::STATE_NEGATIVE;
		} else {
			$result['_cache_state'] = WPML_Single_Url_Cache_Entry::STATE_NEGATIVE;
		}

		return $result;
	}

	private function default_result( $url, $source_language ) {
		return [
			'url'                => $url,
			'source_post_id'     => null,
			'translated_post_id' => null,
			'source_language'    => $source_language,
			'resolution_state'   => WPML_Single_Url_Cache_Entry::PUBLIC_UNCHANGED,
		];
	}

	private function for_current_reader( array $result, $url, $target_language ) {
		if (
			! isset( $result['resolution_state'], $result['url'] )
			|| WPML_Single_Url_Cache_Entry::PUBLIC_RESOLVED !== $result['resolution_state']
			|| empty( $result['translated_post_id'] )
			|| ! is_string( $result['url'] )
		) {
			return $result;
		}

		$translated_post_id = (int) $result['translated_post_id'];
		$status             = get_post_status( $translated_post_id );
		if ( ! is_string( $status ) || 'publish' === $status || is_post_status_viewable( $status ) ) {
			return $result;
		}

		return $this->has_address_for_its_readers( $status ) && current_user_can( 'read_post', $translated_post_id )
			? $this->restore_private_permalink_for_current_user( $result, $url, $target_language )
			: $this->hide_permalink_from_current_user( $result, $url, $target_language );
	}

	private function hide_permalink_from_current_user( array $result, $url, $target_language ) {
		$translated_post_id = (int) $result['translated_post_id'];
		$permalink          = $this->get_permalink_in_language( $translated_post_id, $target_language );
		if ( ! is_string( $permalink ) || '' === $permalink ) {
			$result['url']              = $url;
			$result['resolution_state'] = WPML_Single_Url_Cache_Entry::PUBLIC_UNCHANGED;
			return $result;
		}

		if ( '' !== $this->comparable_path( $permalink ) || $this->is_home_address( $permalink ) ) {
			return $result;
		}

		$result['url'] = $this->in_input_form( $this->with_plain_link_suffix( $permalink, $url ), $url );

		return $result;
	}

	private function with_plain_link_suffix( $permalink, $url ) {
		$permalink_query = wp_parse_url( $permalink, PHP_URL_QUERY );
		$permalink_vars  = [];
		if ( is_string( $permalink_query ) ) {
			parse_str( $permalink_query, $permalink_vars );
		}

		return $this->with_input_suffix(
			$permalink,
			$url,
			array_merge( [ 'p', 'page_id', 'lang' ], array_map( 'strval', array_keys( $permalink_vars ) ) )
		);
	}

	private function restore_private_permalink_for_current_user( array $result, $url, $target_language ) {
		$translated_post_id = (int) $result['translated_post_id'];
		if ( ! $this->is_plain_post_permalink( $result['url'], $translated_post_id ) ) {
			return $result;
		}

		$post_status = get_post_status_object( (string) get_post_status( $translated_post_id ) );
		if ( ! $post_status || empty( $post_status->private ) ) {
			return $result;
		}

		if ( empty( $result['source_post_id'] ) || empty( $result['source_language'] ) ) {
			return $result;
		}

		$permalink = $this->translated_post_url(
			$url,
			(int) $result['source_post_id'],
			$translated_post_id,
			$result['source_language'],
			$target_language
		);
		if ( is_string( $permalink ) && '' !== $permalink ) {
			$result['url'] = $this->in_input_form( $permalink, $url );
		}

		return $result;
	}

	private function is_plain_post_permalink( $url, $post_id ) {
		if ( ! is_string( $url ) || '' === $url || $post_id < 1 ) {
			return false;
		}

		$query = wp_parse_url( $url, PHP_URL_QUERY );
		if ( ! is_string( $query ) || '' === $query ) {
			return false;
		}

		parse_str( $query, $query_vars );

		return (
			! empty( $query_vars['p'] )
			&& (int) $query_vars['p'] === $post_id
		) || (
			! empty( $query_vars['page_id'] )
			&& (int) $query_vars['page_id'] === $post_id
		);
	}

	private function is_plain_link_to( $url, $post_id ) {
		$path = $this->comparable_path( $url );

		return ( '' === $path || 'index.php' === strtolower( $path ) )
			&& $this->is_plain_post_permalink( $url, $post_id );
	}

	private function public_result( array $result, $source_url ) {
		$state = isset( $result['_cache_state'] )
			? $result['_cache_state']
			: WPML_Single_Url_Cache_Entry::STATE_NEGATIVE;

		$result['resolution_state'] = isset( $result['url'] ) && $result['url'] !== $source_url
			? WPML_Single_Url_Cache_Entry::PUBLIC_RESOLVED
			: WPML_Single_Url_Cache_Entry::PUBLIC_UNCHANGED;

		foreach ( array_keys( $result ) as $key ) {
			if ( 0 === strpos( $key, '_' ) ) {
				unset( $result[ $key ] );
			}
		}

		return $result;
	}

	private function get_post_trid( $post_id ) {
		$post_type = get_post_type( $post_id );
		if ( ! $post_type ) {
			return null;
		}

		$trid = $this->sitepress->get_element_trid( $post_id, 'post_' . $post_type );
		return $trid ? (int) $trid : null;
	}

	private function resolve_source_language( $url, $source_language_hint ) {
		$active_languages = array_keys( $this->sitepress->get_active_languages() );
		$default_language = $this->sitepress->get_default_language();

		if ( is_string( $url ) && '' !== $url ) {
			$detected_language = $this->url_converter->get_language_from_url( $url );

			if (
				in_array( $detected_language, $active_languages, true )
				&& $detected_language !== $default_language
			) {
				return $detected_language;
			}
		}

		if ( in_array( $source_language_hint, $active_languages, true ) ) {
			return $source_language_hint;
		}

		return in_array( $default_language, $active_languages, true ) ? $default_language : null;
	}

	private function is_internal_url( $url ) {
		if ( 0 === strpos( $url, '#' ) ) {
			return false;
		}

		$parts = wp_parse_url( $url );
		if ( false === $parts ) {
			return false;
		}

		$scheme = is_array( $parts ) && isset( $parts['scheme'] ) ? strtolower( $parts['scheme'] ) : '';

		if ( '' !== $scheme && ! in_array( $scheme, [ 'http', 'https' ], true ) ) {
			return false;
		}

		if ( '' === $scheme && 0 !== strpos( $url, '//' ) ) {
			return true;
		}

		$absolute_url = 0 === strpos( $url, '//' ) ? 'http:' . $url : $url;

		if ( '' !== $scheme ) {
			$absolute_url = preg_replace( '#^[a-z][a-z0-9+.-]*:#i', $scheme . ':', $absolute_url );
		}

		if ( WPML_Same_Site_Url_Normalizer::is_same_site( $absolute_url ) ) {
			return true;
		}

		$settings = $this->sitepress->get_settings();
		$domains  = isset( $settings['language_domains'] ) && is_array( $settings['language_domains'] )
			? $settings['language_domains']
			: [];
		$url_host = WPML_Same_Site_Url_Normalizer::get_normalized_domain( $absolute_url );

		foreach ( $domains as $domain ) {
			if ( ! is_string( $domain ) || '' === $domain ) {
				continue;
			}
			if ( WPML_Same_Site_Url_Normalizer::get_normalized_domain( $domain ) === $url_host ) {
				return true;
			}
		}

		return false;
	}

	private function is_content_directory_url( $url ) {
		$is_root_relative = 0 === strpos( $url, '/' );
		$is_http_absolute = 1 === preg_match( '#^https?://#i', $url );

		if ( ! $is_root_relative && ! $is_http_absolute ) {
			return false;
		}

		$content_url = content_url();
		if ( ! is_string( $content_url ) || ! $this->is_internal_url( $content_url ) ) {
			return false;
		}

		$content_path = wp_parse_url( $content_url, PHP_URL_PATH );
		$url_path     = wp_parse_url( $url, PHP_URL_PATH );

		if (
			! is_string( $content_path )
			|| '' === $content_path
			|| ! is_string( $url_path )
			|| '' === $url_path
			|| $this->has_ambiguous_path_syntax( $content_path )
			|| $this->has_ambiguous_path_syntax( $url_path )
		) {
			return false;
		}

		$content_path = trim( $content_path, '/' );
		if ( '' === $content_path ) {
			return false;
		}

		$content_path = '/' . $content_path;
		$url_path     = '/' . ltrim( $url_path, '/' );

		return $content_path === $url_path
			|| 0 === strpos( $url_path, $content_path . '/' );
	}

	private function has_ambiguous_path_syntax( $path ) {
		if ( false !== strpos( $path, '%' ) || false !== strpos( $path, '\\' ) ) {
			return true;
		}

		$segments = explode( '/', $path );

		return in_array( '.', $segments, true )
			|| in_array( '..', $segments, true );
	}

	private function convert_scoped( $url, $source_language, $target_language, WPML_Single_Url_Collector $collector ) {
		$previous_language = $this->sitepress->get_current_language();
		if ( $previous_language !== $target_language ) {
			$this->sitepress->switch_lang( $target_language );
		}

		try {
			$converted_url = $this->translate_link_targets->convert_url_for_language(
				$url,
				$source_language,
				$collector
			);
		} finally {
			if ( $previous_language !== $target_language ) {
				$this->sitepress->switch_lang();
			}
		}

		return $converted_url;
	}

	private function scoped_post_id( $url, $source_language, $target_language ) {
		$collector = new WPML_Single_Url_Collector();
		$this->convert_scoped( $url, $source_language, $target_language, $collector );

		return (int) $collector->get_post_id();
	}

	private function page_number_owner( $url, $parsed_id, $source_language, $target_language ) {
		if ( null !== $this->path_suffix( $url, (int) $parsed_id, $source_language, $target_language ) ) {
			return (int) $parsed_id;
		}

		return $this->paged_post_id( $url, $source_language, $target_language )
			?: $this->endpoint_owner( $url, $source_language, $target_language );
	}

	private function paged_post_id( $url, $source_language, $target_language ) {
		$front_page_id = $this->front_page_number_owner( $url, $source_language, $target_language );
		if ( $front_page_id ) {
			return $front_page_id;
		}

		$base_url = $this->without_last_segments( $url, 1 );
		if ( null === $base_url ) {
			return 0;
		}

		$base_id = $this->scoped_post_id( $base_url, $source_language, $target_language );

		return $base_id && '' === $this->path_suffix( $base_url, $base_id, $source_language, $target_language )
			? $base_id
			: 0;
	}

	private function front_page_number_owner( $url, $source_language, $target_language ) {
		if ( ! $this->is_front_page_number_path( $this->comparable_path( $url ) ) ) {
			return 0;
		}

		$previous_language = $this->sitepress->get_current_language();
		if ( $previous_language !== $source_language ) {
			$this->sitepress->switch_lang( $source_language );
		}

		try {
			$page_on_front = (int) get_option( 'page_on_front' );
		} finally {
			if ( $previous_language !== $source_language ) {
				$this->sitepress->switch_lang();
			}
		}

		return $page_on_front > 0 && null !== $this->path_suffix( $url, $page_on_front, $source_language, $target_language )
			? $page_on_front
			: 0;
	}

	private function endpoint_owner( $url, $source_language, $target_language ) {
		$segments = explode( '/', $this->comparable_path( $url ) );

		foreach ( [ 1, 2 ] as $count ) {
			if ( count( $segments ) <= $count ) {
				break;
			}

			$slug = $segments[ count( $segments ) - $count ];
			if ( null === $this->endpoint_slugs->in_language( $slug, $source_language, $target_language ) ) {
				continue;
			}

			$base_url = $this->without_last_segments( $url, $count );
			$base_id  = null === $base_url ? 0 : $this->scoped_post_id( $base_url, $source_language, $target_language );
			if ( $base_id && null !== $this->path_suffix( $url, $base_id, $source_language, $target_language ) ) {
				return $base_id;
			}
		}

		return 0;
	}

	private function without_last_segments( $url, $count ) {
		$comparable_path = $this->comparable_path( $url );
		if ( '' === $comparable_path || count( explode( '/', $comparable_path ) ) <= $count ) {
			return null;
		}

		$path      = (string) wp_parse_url( $url, PHP_URL_PATH );
		$segments  = explode( '/', trim( $path, '/' ) );
		$base_path = '/' . implode( '/', array_slice( $segments, 0, -$count ) ) . '/';

		return (string) preg_replace_callback(
			'#^((?:[a-z][a-z0-9+.-]*:)?//[^/?\#]*)?' . preg_quote( $path, '#' ) . '#i',
			function ( $matches ) use ( $base_path ) {
				return ( isset( $matches[1] ) ? $matches[1] : '' ) . $base_path;
			},
			$url,
			1
		);
	}

	private function get_front_page_id( $url, $source_language ) {
		$previous_language = $this->sitepress->get_current_language();
		if ( $previous_language !== $source_language ) {
			$this->sitepress->switch_lang( $source_language );
		}

		try {
			$page_on_front  = (int) get_option( 'page_on_front' );
			$front_page_url = $page_on_front > 0 ? $this->get_permalink_without_current_post( $page_on_front ) : false;
		} finally {
			if ( $previous_language !== $source_language ) {
				$this->sitepress->switch_lang();
			}
		}

		if ( $page_on_front < 1 ) {
			return 0;
		}

		if ( ! is_string( $front_page_url ) ) {
			return 0;
		}

		$front_page_url = WPML_Same_Site_Url_Normalizer::normalize_url( $front_page_url );
		$url            = WPML_Same_Site_Url_Normalizer::normalize_url( $url );

		return trailingslashit( $front_page_url ) === trailingslashit( $url ) ? $page_on_front : 0;
	}

	private function get_query_post_id( $url ) {
		$query = wp_parse_url( $url, PHP_URL_QUERY );
		if ( ! is_string( $query ) || '' === $query ) {
			return 0;
		}

		parse_str( $query, $query_vars );
		foreach ( [ 'p', 'page_id' ] as $query_var ) {
			if ( ! empty( $query_vars[ $query_var ] ) ) {
				$post_id = absint( $query_vars[ $query_var ] );
				if ( $post_id && get_post( $post_id ) ) {
					return $post_id;
				}
			}
		}

		return 0;
	}

	private function resolve_native_post_id( $url, $source_language ) {
		$previous_language = $this->sitepress->get_current_language();
		$filter_priority   = has_filter( 'url_to_postid', [ $this->sitepress, 'url_to_postid' ] );
		$post_id           = 0;
		$home              = untrailingslashit( (string) get_option( 'home' ) );
		$home_url          = function ( $home_url, $path = '' ) use ( $home ) {
			return is_string( $path ) && '' !== $path ? $home . '/' . ltrim( $path, '/' ) : $home;
		};

		if ( $previous_language !== $source_language ) {
			$this->sitepress->switch_lang( $source_language );
		}
		if ( false !== $filter_priority ) {
			remove_filter( 'url_to_postid', [ $this->sitepress, 'url_to_postid' ], $filter_priority );
		}
		add_filter( 'home_url', $home_url, PHP_INT_MAX, 2 );

		try {
			$lookup_url = $this->without_language( $url, $home );
			$post_id    = function_exists( 'wpcom_vip_url_to_postid' )
				? wpcom_vip_url_to_postid( $lookup_url )
				: url_to_postid( $lookup_url );
		} finally {
			remove_filter( 'home_url', $home_url, PHP_INT_MAX );
			if ( false !== $filter_priority ) {
				add_filter( 'url_to_postid', [ $this->sitepress, 'url_to_postid' ], $filter_priority );
			}
			if ( $previous_language !== $source_language ) {
				$this->sitepress->switch_lang();
			}
		}

		return $post_id > 0 ? (int) $post_id : 0;
	}

	private function without_language( $url, $home ) {
		$path  = $this->comparable_path( $url );
		$query = wp_parse_url( $url, PHP_URL_QUERY );

		$lookup_url = '' === $path
			? $home . '/'
			: $home . '/' . implode( '/', array_map( 'rawurlencode', explode( '/', $path ) ) ) . '/';

		return is_string( $query ) && '' !== $query ? $lookup_url . '?' . $query : $lookup_url;
	}

	private function translate_post_id( $source_id, $target_language ) {
		$post_type = get_post_type( $source_id );
		if ( ! $post_type ) {
			return null;
		}

		$translated_id = apply_filters(
			'wpml_object_id',
			$source_id,
			$post_type,
			false,
			$target_language
		);

		return $translated_id ? (int) $translated_id : null;
	}

	private function translate_term_id( $source_id, $taxonomy, $target_language ) {
		$translated_id = apply_filters(
			'wpml_object_id',
			$source_id,
			$taxonomy,
			false,
			$target_language
		);

		return $translated_id ? (int) $translated_id : null;
	}

	private function resolved_term_url( $converted_url, $url, $term, $translated_id, $target_language ) {
		if (
			is_string( $converted_url )
			&& '' !== $converted_url
			&& ! $this->is_unconverted_sticky_link( $converted_url )
		) {
			return $converted_url;
		}

		if ( ! $translated_id || ! $term instanceof WP_Term ) {
			return null;
		}

		$term_link = $this->get_term_link_in_language( (int) $translated_id, $term->taxonomy, $target_language );
		if ( ! is_string( $term_link ) || '' === $term_link ) {
			return null;
		}

		return $this->with_input_suffix( $term_link, $url );
	}

	private function with_input_suffix( $base_url, $input_url, array $drop_query_keys = [] ) {
		$query    = wp_parse_url( $input_url, PHP_URL_QUERY );
		$fragment = wp_parse_url( $input_url, PHP_URL_FRAGMENT );

		if ( is_string( $query ) && '' !== $query && $drop_query_keys ) {
			$query = implode(
				'&',
				array_filter(
					explode( '&', $query ),
					function ( $pair ) use ( $drop_query_keys ) {
						return '' !== $pair && ! in_array( $this->query_key_as_php_reads_it( $pair ), $drop_query_keys, true );
					}
				)
			);
		}

		if ( is_string( $query ) && '' !== $query ) {
			$base_url .= ( false === strpos( $base_url, '?' ) ? '?' : '&' ) . $query;
		}
		if ( is_string( $fragment ) && '' !== $fragment ) {
			$base_url .= '#' . $fragment;
		}

		return $base_url;
	}

	private function query_key_as_php_reads_it( $pair ) {
		parse_str( $pair, $query_vars );
		$key = key( $query_vars );

		return null === $key ? '' : (string) $key;
	}

	private function is_unconverted_sticky_link( $converted_url ) {
		$query = wp_parse_url( $converted_url, PHP_URL_QUERY );
		if ( ! is_string( $query ) || '' === $query ) {
			return false;
		}

		$args = [];
		parse_str( $query, $args );

		return isset( $args['cat_ID'] );
	}

	private function get_term_link_in_language( $term_id, $taxonomy, $language ) {
		$previous_language = $this->sitepress->get_current_language();
		if ( $previous_language !== $language ) {
			$this->sitepress->switch_lang( $language );
		}

		try {
			$term_link = get_term_link( $term_id, $taxonomy );
		} finally {
			if ( $previous_language !== $language ) {
				$this->sitepress->switch_lang();
			}
		}

		return is_wp_error( $term_link ) ? '' : $term_link;
	}

	private function get_term_trid( WP_Term $term ) {
		$trid = $this->sitepress->get_element_trid(
			(int) $term->term_taxonomy_id,
			'tax_' . $term->taxonomy
		);

		return $trid ? (int) $trid : null;
	}

	private function get_permalink_in_language( $post_id, $language ) {
		$previous_language = $this->sitepress->get_current_language();
		if ( $previous_language !== $language ) {
			$this->sitepress->switch_lang( $language );
		}

		try {
			$permalink = $this->get_permalink_without_current_post( $post_id );
		} finally {
			if ( $previous_language !== $language ) {
				$this->sitepress->switch_lang();
			}
		}

		return $permalink;
	}

	private function get_permalink_without_current_post( $post_id ) {
		if ( ! isset( $GLOBALS['post'] ) ) {
			return get_permalink( $post_id );
		}

		$current_post = $GLOBALS['post'];
		$GLOBALS['post'] = null;

		try {
			return get_permalink( $post_id );
		} finally {
			$GLOBALS['post'] = $current_post;
		}
	}

	private function get_viewable_permalink_in_language( $post_id, $language ) {
		$permalink = $this->get_permalink_in_language( $post_id, $language );
		if ( ! is_string( $permalink ) || '' !== $this->comparable_path( $permalink ) ) {
			return $permalink;
		}

		$post = get_post( $post_id );
		if (
			! $post instanceof WP_Post
			|| '' === (string) $post->post_name
			|| ! $this->has_hidden_permalink( $post->post_status )
		) {
			return $permalink;
		}

		$viewable_post              = clone $post;
		$viewable_post->post_status = 'publish';

		return $this->get_permalink_in_language( $viewable_post, $language );
	}

	private function has_hidden_permalink( $post_status ) {
		if ( 'publish' === $post_status ) {
			return false;
		}
		if ( 'future' === $post_status ) {
			return true;
		}

		$status = get_post_status_object( $post_status );

		return $status && ( ! empty( $status->private ) || ! empty( $status->protected ) );
	}

	private function has_address_for_its_readers( $post_status ) {
		if ( 'future' === $post_status ) {
			return true;
		}

		$status = get_post_status_object( $post_status );

		return $status && ! empty( $status->private );
	}

	private function translated_post_url( $url, $source_id, $translated_id, $source_language, $target_language ) {
		$suffix = $this->path_suffix( $url, $source_id, $source_language, $target_language );
		if ( null === $suffix ) {
			return null;
		}

		$status           = get_post_status( $translated_id );
		$plain_for_anyone = is_string( $status )
			&& $this->has_hidden_permalink( $status )
			&& ! $this->has_address_for_its_readers( $status );
		$permalink        = $plain_for_anyone
			? $this->get_permalink_in_language( $translated_id, $target_language )
			: $this->get_viewable_permalink_in_language( $translated_id, $target_language );
		if ( ! is_string( $permalink ) || '' === $permalink ) {
			return '';
		}

		if ( $plain_for_anyone && '' === $this->comparable_path( $permalink ) && ! $this->is_home_address( $permalink ) ) {
			return $this->with_plain_link_suffix( $permalink, $url );
		}

		if ( '' !== $suffix ) {
			if (
				'' === $this->comparable_path( $permalink )
				&& ! ( $this->is_home_address( $permalink ) && $this->is_front_page_number_path( $suffix ) )
			) {
				return null;
			}
			$permalink = $this->with_path_suffix( $permalink, $suffix );
		}

		return $this->with_input_suffix( $permalink, $url, [ 'p', 'page_id', 'lang' ] );
	}

	private function with_path_suffix( $permalink, $suffix ) {
		$parts     = explode( '?', $permalink, 2 );
		$permalink = user_trailingslashit( trailingslashit( $parts[0] ) . $suffix );

		return isset( $parts[1] ) ? $permalink . '?' . $parts[1] : $permalink;
	}

	private function path_suffix( $url, $source_id, $source_language, $target_language ) {
		$input_path = $this->comparable_path( $url );
		if ( '' === $input_path || 'index.php' === strtolower( $input_path ) ) {
			return '';
		}

		$permalink = $this->get_viewable_permalink_in_language( $source_id, $source_language );
		if ( ! is_string( $permalink ) || '' === $permalink ) {
			return null;
		}

		$post_path = $this->comparable_path( $permalink );
		if ( '' === $post_path ) {
			if ( ! $this->is_home_address( $permalink ) ) {
				return null;
			}

			$page_path = get_page_uri( $source_id );
			if ( is_string( $page_path ) && strtolower( $input_path ) === strtolower( trim( rawurldecode( $page_path ), '/' ) ) ) {
				return '';
			}

			return $this->is_front_page_number_path( $input_path ) ? $input_path : null;
		}

		$input_lower = strtolower( $input_path );
		$post_lower  = strtolower( $post_path );
		if ( $input_lower === $post_lower ) {
			return '';
		}
		if ( 0 !== strpos( $input_lower, $post_lower . '/' ) ) {
			return null;
		}

		$extra = substr( $input_path, strlen( $post_path ) + 1 );

		return $this->is_post_endpoint_suffix( $extra )
			? $extra
			: $this->endpoint_suffix( $url, $extra, $source_language, $target_language );
	}

	private function endpoint_suffix( $url, $extra, $source_language, $target_language ) {
		$segments = explode( '/', $extra );
		if ( count( $segments ) > 2 ) {
			return null;
		}

		$slug = $this->endpoint_slugs->in_language( $segments[0], $source_language, $target_language );
		if ( null === $slug ) {
			return null;
		}

		$written = array_slice( explode( '/', trim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' ) ), - count( $segments ) );
		if ( rawurldecode( implode( '/', $written ) ) !== $extra ) {
			$written = $segments;
		}

		$written[0] = $slug === $segments[0]
			? $written[0]
			: implode( '/', array_map( 'rawurlencode', explode( '/', $slug ) ) );

		return implode( '/', $written );
	}

	private function comparable_path( $url ) {
		$path = wp_parse_url( $url, PHP_URL_PATH );
		if ( ! is_string( $path ) ) {
			return '';
		}

		$path      = trim( rawurldecode( $path ), '/' );
		$home_path = wp_parse_url( (string) get_option( 'home' ), PHP_URL_PATH );
		$home_path = is_string( $home_path ) ? trim( $home_path, '/' ) : '';
		if ( '' !== $home_path ) {
			if ( strtolower( $path ) === strtolower( $home_path ) ) {
				return '';
			}
			if ( 0 === stripos( $path, $home_path . '/' ) ) {
				$path = substr( $path, strlen( $home_path ) + 1 );
			}
		}

		if ( WPML_LANGUAGE_NEGOTIATION_TYPE_DIRECTORY === (int) $this->sitepress->get_setting( 'language_negotiation_type' ) ) {
			$segments = explode( '/', $path, 2 );
			if ( array_key_exists( $segments[0], $this->sitepress->get_active_languages() ) ) {
				$path = isset( $segments[1] ) ? $segments[1] : '';
			}
		}

		return trim( $path, '/' );
	}

	private function is_home_address( $permalink ) {
		$query = wp_parse_url( $permalink, PHP_URL_QUERY );
		if ( ! is_string( $query ) || '' === $query ) {
			return true;
		}

		parse_str( $query, $query_vars );
		unset( $query_vars['lang'] );

		return ! $query_vars;
	}

	private function is_front_page_number_path( $path ) {
		global $wp_rewrite;

		$pagination_base = isset( $wp_rewrite->pagination_base ) && is_string( $wp_rewrite->pagination_base ) && '' !== $wp_rewrite->pagination_base
			? $wp_rewrite->pagination_base
			: 'page';

		return 1 === preg_match( '#^' . preg_quote( $pagination_base, '#' ) . '/\d+$#', $path );
	}

	private function is_post_endpoint_suffix( $extra ) {
		global $wp_rewrite;

		$extra    = trim( $extra, '/' );
		$feeds    = isset( $wp_rewrite->feeds ) && is_array( $wp_rewrite->feeds )
			? $wp_rewrite->feeds
			: [ 'feed', 'rdf', 'rss', 'rss2', 'atom' ];
		$comments = isset( $wp_rewrite->comments_pagination_base ) && is_string( $wp_rewrite->comments_pagination_base )
			? $wp_rewrite->comments_pagination_base
			: 'comment-page';
		$feed     = implode(
			'|',
			array_map(
				function ( $name ) {
					return preg_quote( (string) $name, '#' );
				},
				$feeds
			)
		);

		if ( preg_match( '#^(?:\d+|' . preg_quote( $comments, '#' ) . '-\d+|embed|trackback|feed(?:/(?:' . $feed . '))?|' . $feed . ')$#', $extra ) ) {
			return true;
		}

		return false;
	}

	private function ends_in_page_number( $url ) {
		$path     = trim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' );
		$segments = explode( '/', $path );

		return '' !== $path && ctype_digit( (string) end( $segments ) );
	}

	private function in_input_form( $resolved_url, $input_url ) {
		if (
			$resolved_url === $input_url
			|| 0 === strpos( $input_url, '//' )
			|| null !== wp_parse_url( $input_url, PHP_URL_HOST )
		) {
			return $resolved_url;
		}

		$resolved_host = wp_parse_url( $resolved_url, PHP_URL_HOST );
		$home_host     = wp_parse_url( (string) get_option( 'home' ), PHP_URL_HOST );
		if ( ! is_string( $resolved_host ) || ! is_string( $home_host ) || strtolower( $resolved_host ) !== strtolower( $home_host ) ) {
			return $resolved_url;
		}

		$relative = (string) preg_replace( '#^[a-z][a-z0-9+.-]*://[^/?\#]+#i', '', $resolved_url );

		return '' === $relative ? '/' : $relative;
	}
}
