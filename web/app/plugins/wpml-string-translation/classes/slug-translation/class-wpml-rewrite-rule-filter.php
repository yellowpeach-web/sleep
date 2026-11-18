<?php

class WPML_Rewrite_Rule_Filter implements IWPML_ST_Rewrite_Rule_Filter {
	private $custom_types_repositories;

	private $new_match_finder;

	private $query_var_owners;

	public function __construct( array $custom_types_repositories, WPML_ST_Slug_New_Match_Finder $new_match_finder, $query_var_owners = null ) {
		$this->custom_types_repositories = $custom_types_repositories;
		$this->new_match_finder          = $new_match_finder;
		$this->query_var_owners          = is_array( $query_var_owners ) ? $query_var_owners : null;
	}


	function rewrite_rules_filter( $rules ) {
		if ( ! is_array( $rules ) && empty( $rules ) ) {
			return $rules;
		}

		$custom_types = $this->get_custom_types();
		if ( ! $custom_types ) {
			return $rules;
		}

		$rules = $this->normalize( $rules, $custom_types );

		$result = array();
		foreach ( $rules as $match => $query ) {
			$new_match                         = $this->new_match_finder->get( $match, $custom_types );
			$result[ $new_match->get_value() ] = $query;

			if ( $new_match->should_preserve_original() ) {
				$result[ $match ] = $query;
			}
		}

		return $result;
	}

	public function normalize_rules( $rules ) {
		if ( ! is_array( $rules ) || empty( $rules ) ) {
			return $rules;
		}

		return $this->normalize( $rules, $this->get_custom_types() );
	}

	private function normalize( array $rules, array $custom_types ) {
		$to_original = $this->get_translation_to_original_types( $custom_types );
		if ( ! $to_original ) {
			return $rules;
		}

		$result = array();
		foreach ( $rules as $match => $query ) {
			$normalized = $match;
			foreach ( $to_original as $custom_type ) {
				$slug = trim( (string) $custom_type->get_slug(), '/' );
				if ( '' === $slug || false === strpos( $match, $slug ) ) {
					continue;
				}
				if ( $this->queries_another_type( $query, $custom_type->get_name() ) ) {
					continue;
				}

				$candidate = $this->new_match_finder->find_match_of_type( $match, $custom_type )->get_value();
				if ( $candidate !== $match ) {
					$normalized = $candidate;
					break;
				}
			}

			if ( ! array_key_exists( $normalized, $result ) ) {
				$result[ $normalized ] = $query;
			}
		}

		return $result;
	}

	private function queries_another_type( $query, $type_name ) {
		if ( ! is_string( $query ) ) {
			return false;
		}

		$owners  = $this->get_query_var_owners();
		$another = false;
		$pos     = strpos( $query, '?' );
		$pairs   = explode( '&', false === $pos ? $query : substr( $query, $pos + 1 ) );
		foreach ( $pairs as $pair ) {
			$parts = explode( '=', $pair, 2 );
			$var   = $parts[0];
			$value = isset( $parts[1] ) ? $parts[1] : '';

			if ( 'post_type' === $var && '' !== $value && '$' !== $value[0] ) {
				$owner = $value;
			} elseif ( isset( $owners[ $var ] ) ) {
				$owner = $owners[ $var ];
			} else {
				continue;
			}

			if ( $owner === $type_name ) {
				return false;
			}
			$another = true;
		}

		return $another;
	}

	private function get_query_var_owners() {
		if ( null === $this->query_var_owners ) {
			$this->query_var_owners = array();
			$registered             = array();
			if ( function_exists( 'get_taxonomies' ) ) {
				$registered[] = get_taxonomies( array(), 'objects' );
			}
			if ( function_exists( 'get_post_types' ) ) {
				$registered[] = get_post_types( array(), 'objects' );
			}
			foreach ( $registered as $types ) {
				foreach ( (array) $types as $name => $type ) {
					if ( is_object( $type ) && ! empty( $type->query_var ) && is_string( $type->query_var ) ) {
						$this->query_var_owners[ $type->query_var ] = (string) $name;
					}
				}
			}
		}

		return $this->query_var_owners;
	}

	private function get_translation_to_original_types( array $custom_types ) {
		$originals = array();
		foreach ( $custom_types as $custom_type ) {
			$originals[] = trim( $custom_type->get_slug(), '/' );
		}

		$types = array();
		foreach ( $custom_types as $custom_type ) {
			$original = trim( $custom_type->get_slug(), '/' );
			if ( '' === $original ) {
				continue;
			}

			$values = $custom_type->get_slug_translation_values();
			if ( '' !== $custom_type->get_registered_slug() ) {
				$values[] = $custom_type->get_registered_slug();
			}

			foreach ( array_unique( $values ) as $value ) {
				if ( in_array( $value, $originals, true ) ) {
					continue;
				}

				$reverse = new WPML_ST_Slug_Custom_Type( $custom_type->get_name(), false, $value, $original );
				if ( ! $reverse->is_using_tags() ) {
					$types[] = $reverse;
				}
			}
		}

		return $types;
	}

	private function get_custom_types() {
		if ( empty( $this->custom_types_repositories ) ) {
			return array();
		}

		$types = array();
		foreach ( $this->custom_types_repositories as $repository ) {
			$types[] = $repository->get();
		}

		return call_user_func_array( 'array_merge', $types );
	}
}
