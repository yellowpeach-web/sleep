<?php

class WPML_PB_Reuse_Translations {

	private const MINIMUM_SAMENESS_PERCENT = 50;

	private $string_factory;

	private $original_strings;

	private $current_strings;

	private $get_previous_forms;

	private $current_values_by_string_id = array();

	private $leftover_values_by_string_id = array();

	public function __construct( WPML_ST_String_Factory $string_factory, ?callable $get_previous_forms = null ) {
		$this->string_factory     = $string_factory;
		$this->get_previous_forms = $get_previous_forms;
	}

	public function find_and_reuse_translations( array $original_strings, array $current_strings, array $leftover_strings ) {
		$this->original_strings             = $original_strings;
		$this->current_strings              = $current_strings;
		$this->current_values_by_string_id  = $this->index_values_by_string_id( $current_strings );
		$this->leftover_values_by_string_id = $this->index_values_by_string_id(
			array_filter(
				$leftover_strings,
				function ( array $leftover_string ) {
					return ! $this->is_value_rewritten_under_its_name( $leftover_string );
				}
			)
		);
		$new_strings                        = $this->find_new_strings();
		$new_strings_to_update              = $this->find_existing_strings_for_new_strings( $new_strings, $leftover_strings );
		$this->reuse_translations( $new_strings_to_update );
	}

	private function find_new_strings() {
		$new_strings = array();

		foreach ( $this->current_strings as $current_string ) {
			$found = false;
			foreach ( $this->original_strings as $original_string ) {
				if ( $current_string['id'] == $original_string['id'] ) {
					$found = true;
					break;
				}
			}
			if ( ! $found ) {
				$new_strings[ $current_string['id'] ] = 0;
			}
		}

		return $new_strings;
	}

	private function find_existing_strings_for_new_strings( array $new_strings, array $leftover_strings ) {

		list( $found, $leftover_strings ) = $this->find_by_previous_form( $new_strings, $leftover_strings );
		$new_strings                      = array_diff_key( $new_strings, $found );

		list( $found_identical, $leftover_strings ) = $this->find_by_identical_text( $new_strings, $leftover_strings );
		$found                                     += $found_identical;
		$new_strings                                = array_diff_key( $new_strings, $found_identical );

		list( $new_strings, $leftover_strings ) = $this->find_by_location( $new_strings, $leftover_strings );
		$new_strings = $this->find_by_similar_text( $new_strings, $leftover_strings );

		return $found + $new_strings;
	}

	private function find_by_previous_form( array $new_strings, array $leftover_strings ) {
		$found = array();

		if ( ! $this->get_previous_forms || ! $leftover_strings ) {
			return array( $found, $leftover_strings );
		}

		foreach ( $this->current_strings as $current_string ) {
			$id = $current_string['id'];
			if ( ! isset( $new_strings[ $id ] ) || isset( $found[ $id ] ) || ! isset( $current_string['value'] ) ) {
				continue;
			}

			$forms = call_user_func( $this->get_previous_forms, $current_string['value'] );

			foreach ( $leftover_strings as $key => $leftover_string ) {
				if ( isset( $leftover_string['value'] ) && in_array( $leftover_string['value'], $forms, true ) ) {
					$found[ $id ] = $leftover_string['id'];
					unset( $leftover_strings[ $key ] );
					break;
				}
			}
		}

		return array( $found, $leftover_strings );
	}

	private function index_values_by_string_id( array $strings ) {
		$values = array();
		foreach ( $strings as $string ) {
			if ( isset( $string['id'], $string['value'] ) ) {
				$values[ (int) $string['id'] ] = (string) $string['value'];
			}
		}

		return $values;
	}

	private function is_value_rewritten_under_its_name( array $string ) {
		return isset( $string['name'], $string['value'] )
			&& preg_match( '/^[a-f0-9]{32}$/', (string) $string['name'] )
			&& md5( (string) $string['value'] ) !== $string['name'];
	}

	private function find_by_identical_text( array $new_strings, array $leftover_strings ) {
		$found = array();

		foreach ( array_keys( $new_strings ) as $new_string_id ) {
			foreach ( $leftover_strings as $key => $leftover_string ) {
				if ( $this->is_identical_text( $new_string_id, (int) $leftover_string['id'] ) ) {
					$found[ $new_string_id ] = (int) $leftover_string['id'];
					unset( $leftover_strings[ $key ] );
					break;
				}
			}
		}

		return array( $found, $leftover_strings );
	}

	private function is_identical_text( $new_string_id, $old_string_id ) {
		return (int) $new_string_id !== (int) $old_string_id
			&& isset( $this->current_values_by_string_id[ (int) $new_string_id ], $this->leftover_values_by_string_id[ (int) $old_string_id ] )
			&& $this->current_values_by_string_id[ (int) $new_string_id ] === $this->leftover_values_by_string_id[ (int) $old_string_id ];
	}

	private function find_by_location( array $new_strings, array $leftover_strings ) {
		if ( ! $leftover_strings ) {
			return array( $new_strings, $leftover_strings );
		}

		if ( ( count( $this->current_strings ) - count( $leftover_strings ) ) !== count( $this->original_strings ) ) {
			return array( $new_strings, $leftover_strings );
		}

		foreach ( $leftover_strings as $key => $leftover_string ) {
			foreach ( $this->current_strings as $current_string ) {
				if ( isset( $new_strings[ $current_string['id'] ] ) ) {
					if ( $this->is_same_location_and_different_ids( $current_string, $leftover_string )
					     && $this->is_similar_text( $leftover_string['value'], $current_string['value'] ) ) {
						$new_strings[ $current_string['id'] ] = $leftover_string['id'];
						unset( $leftover_strings[ $key ] );
					}
				}
			}
		}

		return array( $new_strings, $leftover_strings );
	}

	private function find_by_similar_text( array $new_strings, array $leftover_strings ) {

		if ( $leftover_strings ) {
			foreach ( $new_strings as $new_string_id => $old_string_id ) {
				if ( ! $old_string_id ) {
					$new_string_value = $this->string_factory->find_by_id( $new_string_id )->get_value();
					$best_key         = $this->find_most_similar_leftover_key( $new_string_value, $leftover_strings );
					if ( null !== $best_key ) {
						$new_strings[ $new_string_id ] = $leftover_strings[ $best_key ]['id'];
						unset( $leftover_strings[ $best_key ] );
					}
				}
			}
		}

		return $new_strings;
	}

	private function find_most_similar_leftover_key( $new_string_value, array $leftover_strings ) {
		$best_key      = null;
		$best_sameness = self::MINIMUM_SAMENESS_PERCENT;
		foreach ( $leftover_strings as $key => $leftover_string ) {
			$leftover_string_value = $this->string_factory->find_by_id( $leftover_string['id'] )->get_value();
			$sameness              = WPML_ST_Diff::get_sameness_percent( $leftover_string_value, $new_string_value );

			if ( $sameness > $best_sameness ) {
				$best_key      = $key;
				$best_sameness = $sameness;
			}
		}

		return $best_key;
	}

	private function is_same_location_and_different_ids( array $current_string, array $leftover_string ) {
		return $current_string['location'] === $leftover_string['location']
		       && $current_string['id'] !== $leftover_string['id'];
	}

	private function is_similar_text( $old_text, $new_text ) {
		return WPML_ST_Diff::get_sameness_percent( $old_text, $new_text ) > self::MINIMUM_SAMENESS_PERCENT;
	}

	private function reuse_translations( array $strings ) {
		foreach ( $strings as $new_string_id => $old_string_id ) {

			if ( $old_string_id ) {
				$new_string   = $this->string_factory->find_by_id( $new_string_id );
				$old_string   = $this->string_factory->find_by_id( $old_string_id );
				$translations = $old_string->get_translations();

				$should_keep_status = $this->is_identical_text( $new_string_id, $old_string_id );

				foreach ( $translations as $translation ) {
					$status = ! $should_keep_status && ICL_TM_COMPLETE === (int) $translation->status ? ICL_TM_NEEDS_UPDATE : $translation->status;
					$new_string->set_translation(
						$translation->language,
						$translation->value,
						$status,
						$translation->translator_id,
						$translation->translation_service,
						$translation->batch_id
					);
				}
			}
		}
	}
}
