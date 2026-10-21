<?php

namespace WPML\PB\ElementUid;

class Capture {

	const SOURCE = 'node';

	private $registry;

	private $nameMap;

	private $package;

	private $walked = false;

	private $map = [];

	private $hashes = [];

	public function __construct( Registry $registry, NameMap $nameMap ) {
		$this->registry = $registry;
		$this->nameMap  = $nameMap;
	}

	public function onStart( $package ) {
		$this->reset();

		if ( is_array( $package )
			&& ! empty( $package['post_id'] )
			&& ! empty( $package['kind'] )
			&& array_key_exists( $package['kind'], $this->getSupportedBuilders() )
		) {
			$this->package = $package;
		}
	}

	public function onWalk( $package ) {
		if ( $this->isCapturing( $package ) ) {
			$this->walked = true;
		}
	}

	public function onString( $package, $nodeId, $string, $element ) {
		if ( ! $this->isCapturing( $package ) ) {
			return;
		}

		$uid = $this->resolveUid( (string) $nodeId, $element );

		if ( '' === $uid ) {
			return;
		}

		NameMap::add( $this->map, $string->get_name(), $uid );

		if ( ! isset( $this->hashes[ $uid ] ) ) {
			$this->hashes[ $uid ] = Hash::of( $element );
		}
	}

	private function resolveUid( $nodeId, $element ) {
		$builders = $this->getSupportedBuilders();
		$source   = isset( $this->package['kind'] ) && isset( $builders[ $this->package['kind'] ] )
			? $builders[ $this->package['kind'] ]
			: null;

		if ( null === $source ) {
			return $nodeId;
		}

		$value = (array) $element;

		foreach ( explode( '.', $source ) as $key ) {
			if ( ! is_array( $value ) || ! isset( $value[ $key ] ) ) {
				return '';
			}
			$value = is_object( $value[ $key ] ) ? (array) $value[ $key ] : $value[ $key ];
		}

		return is_scalar( $value ) ? (string) $value : '';
	}

	public function onEnd( $package ) {
		if ( $this->isCapturing( $package ) && $this->walked ) {
			$postId = (int) $this->package['post_id'];

			$this->nameMap->save( $postId, $this->map, self::SOURCE );
			$this->registry->update( $postId, $this->hashes, self::SOURCE );
		}

		$this->reset();
	}

	private function reset() {
		$this->package = null;
		$this->walked  = false;
		$this->map     = [];
		$this->hashes  = [];
	}

	private function isCapturing( $package ) {
		return null !== $this->package
			&& is_array( $package )
			&& isset( $package['kind'], $package['post_id'] )
			&& $package['kind'] === $this->package['kind']
			&& (int) $package['post_id'] === (int) $this->package['post_id'];
	}

	private function getSupportedBuilders() {
		return apply_filters(
			'wpml_pb_element_uid_builders',
			[
				'Elementor'      => null,
				'Beaver builder' => null,
				'SiteOrigin'     => 'panels_info.widget_id',
				'Cornerstone'    => '_id',
			]
		);
	}
}
