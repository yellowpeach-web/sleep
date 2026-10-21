<?php

namespace WPML\PB\ElementUid\Markup;

use WPML\PB\ElementUid\Capture;
use WPML\PB\ElementUid\Hash;
use WPML\PB\Elementor\DataConvert;
use WPML\PB\Elementor\Helper\Node;

class ElementorProvider implements Provider {

	private $nodes;

	public function __construct( $nodes = null ) {
		$this->nodes = $nodes;
	}

	public function appliesTo( $postId ) {
		return ( new \WPML_Elementor_Data_Settings() )->is_handling_post( $postId );
	}

	public function getElements( $postId ) {
		$data = DataConvert::unserialize( get_post_meta( $postId, \WPML_Elementor_Data_Settings::META_KEY_DATA, true ) );

		if ( ! is_array( $data ) ) {
			return [];
		}

		$nodes    = $this->nodes ?: new \WPML_Elementor_Translatable_Nodes();
		$elements = [];

		$this->walk( $data, $nodes, $elements );

		return $elements;
	}

	public function getSource() {
		return Capture::SOURCE;
	}

	private function walk( array $dataArray, $nodes, array &$elements ) {
		foreach ( $dataArray as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}

			if ( Node::isTranslatable( $element ) && ! empty( $element['id'] ) ) {
				$strings = \WPML_Elementor_Translatable_Nodes::with_active_element_settings_cache(
					function () use ( $nodes, $element ) {
						return $nodes->get( $element['id'], $element );
					}
				);

				if ( $strings ) {
					$elements[] = [
						'uid'     => (string) $element['id'],
						'hash'    => Hash::of( $element ),
						'strings' => $strings,
					];
				}
			}

			if ( Node::hasChildren( $element ) ) {
				$this->walk( $element['elements'], $nodes, $elements );
			}
		}
	}
}
