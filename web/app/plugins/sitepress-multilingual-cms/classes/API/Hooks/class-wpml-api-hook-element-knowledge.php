<?php

use WPML\Knowledge\ElementKnowledge;

class WPML_API_Hook_Element_Knowledge implements IWPML_Action {

	const AVAILABLE     = 'wpml_element_knowledge_available';
	const GET           = 'wpml_element_knowledge';
	const RECORDS       = 'wpml_element_knowledge_records';
	const SET           = 'wpml_set_element_knowledge';
	const DELETE        = 'wpml_delete_element_knowledge';
	const DELETE_SCOPE  = 'wpml_delete_element_knowledge_scope';

	private $knowledge;

	public function __construct( ElementKnowledge $knowledge ) {
		$this->knowledge = $knowledge;
	}

	public function add_hooks() {
		add_filter( self::AVAILABLE, [ $this, 'is_available' ] );
		add_filter( self::GET, [ $this, 'get' ], 10, 2 );
		add_filter( self::RECORDS, [ $this, 'get_records' ], 10, 2 );
		add_action( self::SET, [ $this, 'set' ] );
		add_action( self::DELETE, [ $this, 'delete' ] );
		add_action( self::DELETE_SCOPE, [ $this, 'delete_scope' ] );
	}

	public function is_available( $available ) {
		return $this->knowledge->isAvailable();
	}

	public function get( $default, $args ) {
		if ( ! $this->addresses( $args ) || ! isset( $args['name'] ) ) {
			return $default;
		}

		$value = $this->knowledge->get(
			$args['entity_kind'],
			$args['entity_id'],
			isset( $args['part'] ) ? $args['part'] : null,
			$args['name']
		);

		return null === $value ? $default : $value;
	}

	public function get_records( $default, $args ) {
		if ( ! $this->addresses( $args ) ) {
			return $default;
		}

		return $this->knowledge->getEntity(
			$args['entity_kind'],
			$args['entity_id'],
			isset( $args['name'] ) ? $args['name'] : null
		);
	}

	public function set( $args ) {
		if ( ! $this->addresses( $args ) || ! isset( $args['name'] ) || ! array_key_exists( 'value', $args ) ) {
			return;
		}

		$this->knowledge->set(
			$args['entity_kind'],
			$args['entity_id'],
			isset( $args['part'] ) ? $args['part'] : null,
			$args['name'],
			$args['value'],
			isset( $args['scope'] ) ? $args['scope'] : null
		);
	}

	public function delete( $args ) {
		if ( ! $this->addresses( $args ) ) {
			return;
		}

		if ( isset( $args['part'] ) ) {
			if ( isset( $args['name'] ) ) {
				$this->knowledge->delete( $args['entity_kind'], $args['entity_id'], $args['part'], $args['name'] );
			}

			return;
		}

		$this->knowledge->deleteEntity(
			$args['entity_kind'],
			$args['entity_id'],
			isset( $args['name'] ) ? $args['name'] : null
		);
	}

	public function delete_scope( $scope ) {
		if ( is_string( $scope ) && '' !== $scope ) {
			$this->knowledge->deleteScope( $scope );
		}
	}

	private function addresses( $args ) {
		return is_array( $args )
			&& isset( $args['entity_kind'] )
			&& '' !== (string) $args['entity_kind']
			&& isset( $args['entity_id'] )
			&& '' !== (string) $args['entity_id'];
	}
}
