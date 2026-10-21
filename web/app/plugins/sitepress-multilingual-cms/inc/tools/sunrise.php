<?php

class WPML_Sunrise_Lang_In_Domains {

	private $wpdb;

	private $table_prefix;

	private $current_blog;

	private $no_recursion;

	public function init() {
		if ( ! defined( 'WPML_SUNRISE_MULTISITE_DOMAINS' ) ) {
			define( 'WPML_SUNRISE_MULTISITE_DOMAINS', true );
		}

		add_filter( 'query', array( $this, 'query_filter' ) );
	}

	public function query_filter( $q ) {
		$this->set_private_properties();

		if ( ! $this->current_blog && ! $this->no_recursion ) {

			$this->no_recursion = true;

			$domains = $this->extract_variables_from_query( $q, 'domain' );
			$paths   = $this->extract_variables_from_query( $q, 'path' );

			if ( $domains && $this->query_has_no_result( $domains, $paths ) ) {
				$q = $this->transpose_query_if_one_domain_is_matching( $q, $domains );
			}

			$this->no_recursion = false;
		}

		return $q;
	}

	private function set_private_properties() {
		global $wpdb, $table_prefix, $current_blog;

		$this->wpdb         = $wpdb;
		$this->table_prefix = $table_prefix;
		$this->current_blog = $current_blog;

	}

	private function extract_variables_from_query( $query, $field ) {
		$variables = array();
		$patterns  = array(
			'#WHERE\s+' . $field . '\s+IN\s*\(([^\)]+)\)#',
			'#WHERE\s+' . $field . '\s*=\s*([^\s]+)#',
			'#AND\s+' . $field . '\s+IN\s*\(([^\)]+)\)#',
			'#AND\s+' . $field . '\s*=\s*([^\s]+)#',
		);

		foreach ( $patterns as $pattern ) {
			$found = preg_match( $pattern, $query, $matches );
			if ( $found && array_key_exists( 1, $matches ) ) {
				$variables = $matches[1];
				$variables = preg_replace( '/\s+/', '', $variables );
				$variables = preg_replace( '/[\'"]/', '', $variables );
				$variables = explode( ',', $variables );
				break;
			}
		}

		return $variables;
	}

	private function query_has_no_result( array $domains, array $paths ) {
		$wpdb = $this->wpdb;
		$paths = $paths ?: array( null );

		foreach ( $domains as $domain ) {
			foreach ( $paths as $path ) {
				if ( null === $path ) {
					$match = $wpdb->get_row(
						$wpdb->prepare(
							"SELECT blog_id FROM {$wpdb->blogs} WHERE domain = %s LIMIT 1",
							$domain
						)
					);
				} else {
					$match = $wpdb->get_row(
						$wpdb->prepare(
							"SELECT blog_id FROM {$wpdb->blogs} WHERE domain = %s AND path = %s LIMIT 1",
							$domain,
							$path
						)
					);
				}

				if ( $match ) {
					return false;
				}
			}
		}

		return true;
	}

	private function transpose_query_if_one_domain_is_matching( $q, $domains ) {
		$wpdb = $this->wpdb;

		$paths = $this->extract_variables_from_query( $q, 'path' );

		$blogs = array_map(
			'intval',
			(array) get_sites(
				array(
					'fields'                 => 'ids',
					'number'                 => 0,
					'orderby'                => 'id',
					'order'                  => 'ASC',
					'path__in'               => $paths,
					'update_site_cache'      => false,
					'update_site_meta_cache' => false,
				)
			)
		);

		$default_site_key = array_search( (int) BLOG_ID_CURRENT_SITE, $blogs, true );
		if ( false !== $default_site_key ) {
			unset( $blogs[ $default_site_key ] );
			$blogs[] = (int) BLOG_ID_CURRENT_SITE;
		}

		$found_blog_id = null;
		foreach ( (array) $blogs as $blog_id ) {
			$prefix = $this->table_prefix;

			if ( $blog_id > 1 ) {
				$prefix .= (int) $blog_id . '_';
			}

			if ( 1 !== preg_match( '/^[A-Za-z0-9_]+$/D', $prefix ) ) {
				continue;
			}

			$icl_settings = $this->wpdb->get_var(
				"SELECT option_value FROM " . esc_sql( $prefix ) . "options WHERE option_name = 'icl_sitepress_settings'"
			);

			if ( $icl_settings ) {
				$icl_settings = unserialize( $icl_settings, [ 'allowed_classes' => false ] );

				if (
					is_array( $icl_settings )
					&& isset( $icl_settings['language_negotiation_type'], $icl_settings['language_domains'] )
					&& is_array( $icl_settings['language_domains'] )
					&& 2 === (int) $icl_settings['language_negotiation_type']
				) {
					$found_blog_id = $this->get_blog_id_from_domain( $domains, $icl_settings, $blog_id );
					if ( $found_blog_id ) {
						$q = $this->wpdb->prepare( "SELECT blog_id FROM {$wpdb->blogs} WHERE blog_id = %d", $found_blog_id );
						break;
					}
				}
			}
		}

		return $q;
	}

	private function get_blog_id_from_domain( array $domains, array $wpml_settings, $blog_id ) {
		foreach ( $domains as $domain ) {
			if ( in_array( 'http://' . $domain, $wpml_settings['language_domains'], true ) ) {
				return $blog_id;
			} elseif ( in_array( $domain, $wpml_settings['language_domains'], true ) ) {
				return $blog_id;
			}
		}

		return null;
	}
}

$wpml_sunrise_lang_in_domains = new WPML_Sunrise_Lang_In_Domains();
$wpml_sunrise_lang_in_domains->init();

