<?php

use WPML\PB\TranslationJob\SourceLabels;

abstract class WPML_Page_Builders_Register_Strings {

	private $translatable_nodes;

	protected $data_settings;

	private $string_registration;

	private $reuse_translations;

	private $string_location;

	private $group_index;

	private $collected;

	public function __construct(
		IWPML_Page_Builders_Translatable_Nodes $translatable_nodes,
		IWPML_Page_Builders_Data_Settings $data_settings,
		WPML_PB_String_Registration $string_registration,
		?WPML_PB_Reuse_Translations_By_Strategy $reuse_translations = null
	) {

		$this->data_settings       = $data_settings;
		$this->translatable_nodes  = $translatable_nodes;
		$this->string_registration = $string_registration;
		$this->reuse_translations  = $reuse_translations;
	}

	public function register_strings( WP_Post $post, array $package ) {
		SourceLabels::run(
			function () use ( $post, $package ) {
				$this->register_strings_in_source_labels( $post, $package );
			}
		);
	}

	private function register_strings_in_source_labels( WP_Post $post, array $package ) {

		do_action( 'wpml_start_string_package_registration', $package );

		$this->string_location = 1;
		$this->group_index     = 0;

		if ( $this->data_settings->is_handling_post( $post->ID ) ) {

			if ( $this->reuse_translations ) {
				$existing_strings = $this->reuse_translations->get_strings( $post->ID );
				$this->reuse_translations->set_original_strings( $existing_strings );
			}

			$converted = $this->get_data_to_register( $post );

			if ( is_array( $converted ) ) {
				do_action( 'wpml_pb_register_strings_for_post', $package, $post );

				$this->register_strings_for_modules(
					$converted,
					$package
				);
			}

			if ( $this->reuse_translations ) {
				$this->reuse_translations->find_and_reuse( $post->ID, $existing_strings );
			}
		}

		do_action( 'wpml_delete_unused_package_strings', $package );
	}

	public function collect_strings( WP_Post $post ) {
		if ( ! $this->data_settings->is_handling_post( $post->ID ) ) {
			return [];
		}

		$converted = $this->get_data_to_register( $post );
		if ( ! is_array( $converted ) ) {
			return [];
		}

		$this->collected       = [];
		$this->string_location = 1;
		$this->group_index     = 0;
		try {
			$this->register_strings_for_modules( $converted, [] );
			$collected = $this->collected;
		} finally {
			$this->collected = null;
		}

		return $collected;
	}

	public static function is_text_to_compare( WPML_PB_String $string ) {
		$value = $string->get_value();

		return is_string( $value )
			&& 'LINK' !== $string->get_editor_type()
			&& ! preg_match( '#^\s*(?:[a-z][a-z0-9+.-]*:)?//\S+\s*$#i', $value );
	}

	protected function get_data_to_register( WP_Post $post ) {
		$data = get_post_meta( $post->ID, $this->data_settings->get_meta_field(), false );

		if ( ! $data ) {
			return [];
		}

		return $this->data_settings->convert_data_to_array( $data );
	}

	protected function register_strings_for_node( $node_id, $element, array $package ) {
		$strings = $this->translatable_nodes->get( $node_id, $element );

		if ( null !== $this->collected ) {
			foreach ( $strings as $string ) {
				if ( $string instanceof WPML_PB_String && self::is_text_to_compare( $string ) ) {
					$this->collected[ $string->get_name() ] = $string->get_value();
				}
			}

			return;
		}

		foreach ( $strings as $string ) {
			$string = $this->filter_string_to_register( $string, $node_id, $element, $package );

			do_action( 'wpml_pb_register_string_for_node', $package, (string) $node_id, $string, $element );

			$this->string_registration->register_string(
				$package['post_id'],
				$string->get_value(),
				$string->get_editor_type(),
				$string->get_title(),
				$string->get_name(),
				$this->string_location,
				$string->get_wrap_tag(),
				$this->group_index
			);

			++$this->string_location;
		}

		++$this->group_index;
	}

	protected function filter_string_to_register( WPML_PB_String $string, $node_id, $element, $package ) {
		return $string;
	}

	abstract protected function register_strings_for_modules( array $data_array, array $package );
}
