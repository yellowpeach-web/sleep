<?php

namespace WPML\XMLConfig;

use WPML\FP\Obj;

class AllowTranslatableJobFields extends \WPML_WP_Option implements \IWPML_Backend_Action, \IWPML_Frontend_Action, \IWPML_AJAX_Action {

	private $rules;

	public function get_key() {
		return 'wpml_allow_translatable_job_fields';
	}

	public function get_default() {
		return [];
	}

	public function add_hooks() {
		add_filter( 'wpml_config_array', [ $this, 'wpml_config_filter' ] );
		add_filter( 'wpml_tm_job_field_is_translatable', [ $this, 'filter_job_field' ], 10, 3 );
	}

	public function wpml_config_filter( $config ) {
		$data = Obj::pathOr( [], [ 'wpml-config', 'allow-translatable-job-fields' ], $config );

		$cleaned = wpml_collect( $data )
			->collapse()
			->map( Obj::prop( 'attr' ) )
			->all();

		$this->set( $cleaned );
		$this->rules = (array) $cleaned;

		return $config;
	}

	public function filter_job_field( $isTranslatable, $field, $value ) {
		if ( $isTranslatable ) {
			return $isTranslatable;
		}

		if ( null === $this->rules ) {
			$this->rules = (array) $this->get();
		}
		$config = $this->rules;

		if ( ! $config ) {
			return $isTranslatable;
		}

		$doesPass = function( $rule ) use ( $field, $value ) {
			try {
				return preg_match( $rule['type'], $field['field_type'] ) && preg_match( $rule['value'], $value );
			} catch ( \Exception $e ) {
				return false;
			}
		};

		return (bool) wpml_collect( $config )
			->first( $doesPass );
	}
}
