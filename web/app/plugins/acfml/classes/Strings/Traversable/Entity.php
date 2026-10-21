<?php

namespace ACFML\Strings\Traversable;

use ACFML\Strings\Transformer\Transformer;
use WPML\FP\Obj;

abstract class Entity implements Traversable {

	protected $data = [];

	protected $idKey = 'key';

	public function __construct( array $data, array $context = [] ) {
		$this->data = $this->prepareData( $data, $context );
	}

	protected function prepareData( $data, $context ) {
		return $data;
	}

	public function traverse( Transformer $transformer, $context = null ) {
		foreach ( $this->getFilteredConfig( $context ) as $config ) {
			$key = $config['key'];

			if ( isset( $this->data[ $key ] ) ) {
				$stringData         = $this->getStringData( $config );
				$this->data[ $key ] = $this->transform( $transformer, $this->data[ $key ], $stringData );
			}
		}

		return $this->data;
	}

	protected function transform( Transformer $transformer, $value, $config ) {
		return $transformer->transform( $value, $config );
	}

	abstract protected function getConfig();

	protected function getFilteredConfig( $context = null ) {
		$config = $this->getConfig();

		if ( ! $context ) {
			return $config;
		}

		return wpml_collect( $config )
			->filter( function( $configItem ) use ( $context ) {
				return Obj::prop( 'context', $configItem ) && in_array( $context, Obj::prop( 'context', $configItem ), true );
			} )
			->values()
			->toArray();
	}

	protected function getStringData( $config ) {
		return array_merge( $config, [ 'id' => $this->data[ $this->idKey ] ] );
	}
}
