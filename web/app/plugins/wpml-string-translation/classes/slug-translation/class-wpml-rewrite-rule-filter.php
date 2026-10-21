<?php

class WPML_Rewrite_Rule_Filter implements IWPML_ST_Rewrite_Rule_Filter {
	private $custom_types_repositories;

	private $new_match_finder;

	public function __construct( array $custom_types_repositories, WPML_ST_Slug_New_Match_Finder $new_match_finder ) {
		$this->custom_types_repositories = $custom_types_repositories;
		$this->new_match_finder          = $new_match_finder;
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
