<?php

class WPML_URL_Converter_Url_Helper {
	private $wpdb;

	private $wpml_include_url_filter;

	private $absolute_home;

	public function __construct( ?wpdb $wpdb = null, ?WPML_Include_Url $wpml_include_url_filter = null ) {
		if ( ! $wpdb ) {
			global $wpdb;
		}

		if ( ! $wpml_include_url_filter ) {
			global $wpml_include_url_filter;
		}

		$this->wpdb                    = $wpdb;
		$this->wpml_include_url_filter = $wpml_include_url_filter;
	}

	public function get_abs_home() {
		if ( ! $this->absolute_home ) {
			$is_multisite = is_multisite();
			if ( ! $is_multisite && defined( 'WP_HOME' ) ) {
				$this->absolute_home = $this->home_of_the_default_domain( (string) WP_HOME );
			} elseif ( $is_multisite && ! is_main_site() ) {
				$protocol = preg_match( '/^(https)/', get_option( 'home' ) ) === 1 ? 'https://' : 'http://';
				$wpdb     = $this->wpdb;

				$this->absolute_home = $protocol . $wpdb->get_var(
					$wpdb->prepare(
						"SELECT CONCAT(b.domain, b.path)
						FROM {$wpdb->blogs} b
						WHERE blog_id = %d
						LIMIT 1",
						$wpdb->blogid
					)
				);
			} else {
				$this->absolute_home = $this->get_unfiltered_home_option();
			}
		}

		return apply_filters( 'wpml_url_converter_get_abs_home', $this->absolute_home );
	}

	private function home_of_the_default_domain( $wp_home ) {
		if ( WPML_LANGUAGE_NEGOTIATION_TYPE_DOMAIN !== (int) wpml_get_setting( 'language_negotiation_type' ) ) {
			return $wp_home;
		}

		$domains = wpml_get_setting( 'language_domains' );
		unset( $domains[ (string) wpml_get_setting( 'default_language' ) ] );

		$secondary_domains = array_filter( array_map( array( $this, 'host_and_port' ), is_array( $domains ) ? $domains : array() ) );

		if ( ! in_array( $this->host_and_port( $wp_home ), $secondary_domains, true ) ) {
			return $wp_home;
		}

		$stored_home   = (string) $this->get_unfiltered_home_option();
		$stored_domain = $this->host_and_port( $stored_home );

		return $stored_domain && ! in_array( $stored_domain, $secondary_domains, true ) ? $stored_home : $wp_home;
	}

	private function host_and_port( $url ) {
		$host = WPML_Language_Domains::hostOf( $url );
		if ( ! $host ) {
			return null;
		}

		$url  = (string) $url;
		$port = wp_parse_url( preg_match( '#^[a-z][a-z0-9+.-]*://#i', $url ) ? $url : '//' . ltrim( $url, '/' ), PHP_URL_PORT );

		return $port ? $host . ':' . $port : $host;
	}

	public function is_url_admin( $url ) {
		$url_query_parts = wpml_parse_url( strpos( $url, 'http' ) === false ? 'http://' . $url : $url );

		return isset( $url_query_parts['path'] )
			   && strpos( wpml_strip_subdir_from_url( $url_query_parts['path'] ), '/wp-admin' ) === 0;
	}

	public function get_unfiltered_home_option() {
		if ( $this->wpml_include_url_filter ) {
			return $this->wpml_include_url_filter->get_unfiltered_home();
		} else {
			$wpdb = $this->wpdb;

			return $wpdb->get_var(
				"SELECT option_value
				FROM {$wpdb->options}
				WHERE option_name = 'home'
				LIMIT 1"
			);
		}
	}
}
