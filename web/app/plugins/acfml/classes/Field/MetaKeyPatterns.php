<?php

namespace ACFML\Field;

use ACFML\Helper\Fields;
use WPML\FP\Fns;
use WPML\FP\Obj;

class MetaKeyPatterns implements \IWPML_Backend_Action, \IWPML_Frontend_Action, \IWPML_REST_Action, \IWPML_DIC_Action {

	private const REGEX_DELIMITER = '#';

	private $patterns;

	private $matches = [];

	public function add_hooks() {
		add_filter( 'acf/update_field_group', [ $this, 'invalidate' ], 20 );
		add_filter( 'acf/update_field', [ $this, 'invalidate' ], 20 );
		add_filter( 'acf/delete_field', [ $this, 'invalidate' ], 20 );
		add_filter( 'acf/delete_field_group', [ $this, 'invalidate' ], 20 );

		add_action( 'switch_blog', [ $this, 'invalidateOnBlogSwitch' ], 10, 0 );
	}

	public function invalidate( $value = null ) {
		$this->patterns = null;
		$this->matches  = [];

		return $value;
	}

	public function invalidateOnBlogSwitch() {
		$this->invalidate();
	}

	public function matches( $fieldType, $metaKey ) {
		$memoKey = $fieldType . '|' . $metaKey;
		if ( ! array_key_exists( $memoKey, $this->matches ) ) {
			$this->matches[ $memoKey ] = $this->matchesAPattern( $this->getPatterns()[ $fieldType ] ?? [], $metaKey );
		}

		return $this->matches[ $memoKey ];
	}

	public function matchesAValueField( $metaKey ) {
		foreach ( array_keys( $this->getPatterns() ) as $fieldType ) {
			if ( ! in_array( $fieldType, Fields::WRAPPER_FIELDS, true ) && $this->matches( $fieldType, $metaKey ) ) {
				return true;
			}
		}

		return false;
	}

	private function matchesAPattern( array $patterns, string $metaKey ) {
		foreach ( $patterns as $pattern ) {
			if ( preg_match( self::REGEX_DELIMITER . '^' . $pattern . '$' . self::REGEX_DELIMITER, $metaKey ) ) {
				return true;
			}
		}

		return false;
	}

	private function getPatterns() {
		if ( null === $this->patterns ) {
			$patterns = [];

			$collect = function ( $field, $fieldPattern ) use ( &$patterns ) {
				$patterns[ (string) Obj::prop( 'type', $field ) ][] = $fieldPattern;

				return $field;
			};

			foreach ( acf_get_field_groups() as $fieldGroup ) {
				Fields::iterate( acf_get_fields( $fieldGroup ), $collect, Fns::identity() );
			}

			$this->patterns = $patterns;
		}

		return $this->patterns;
	}
}
