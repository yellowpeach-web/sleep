<?php

class WPML_API_Hook_Permalink implements IWPML_Action {

	private $url_converter;

	private $absolute_resolver;

	public function __construct( WPML_URL_Converter $url_converter, IWPML_Resolve_Object_Url $absolute_resolver ) {
		$this->url_converter     = $url_converter;
		$this->absolute_resolver = $absolute_resolver;
	}

	public function add_hooks() {
		add_filter( 'wpml_permalink', array( $this, 'wpml_permalink_filter' ), 10, 3 );
	}

	public function wpml_permalink_filter( $url, $lang = null, $absolute_url = false ) {
		if ( ! is_string( $url ) || '' === $url ) {
			return $url;
		}

		$resolved_url = $absolute_url ? $this->absolute_resolver->resolve_object_url( $url, $lang ) : false;

		if ( ! $resolved_url && $absolute_url && $this->is_sticky_link_to_deleted_target( $url ) ) {
			return esc_url_raw( $url );
		}

		return esc_url_raw( $resolved_url ?: $this->url_converter->convert_url( $url, $lang ) );
	}

	private function is_sticky_link_to_deleted_target( $url ) {
		$query = parse_url( $url, PHP_URL_QUERY );
		if ( ! is_string( $query ) || '' === $query ) {
			return false;
		}

		parse_str( $query, $query_vars );

		foreach ( [ 'p', 'page_id' ] as $query_var ) {
			if ( self::is_id( $query_vars, $query_var ) ) {
				return ! get_post( (int) $query_vars[ $query_var ] );
			}
		}

		if ( self::is_id( $query_vars, 'cat_ID' ) ) {
			$term = get_term( (int) $query_vars['cat_ID'], 'category' );

			return ! $term || is_wp_error( $term );
		}

		return false;
	}

	private static function is_id( array $query_vars, $query_var ) {
		return isset( $query_vars[ $query_var ] )
			&& is_string( $query_vars[ $query_var ] )
			&& ctype_digit( $query_vars[ $query_var ] )
			&& (int) $query_vars[ $query_var ] > 0;
	}
}
