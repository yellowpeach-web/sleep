<?php

namespace WPML\PB\ElementUid\Markup;

use WPML\PB\Cornerstone\Utils;
use WPML\PB\ElementUid\Capture;
use WPML\PB\ElementUid\Hash;

class CornerstoneProvider implements Provider {

	private $nodes;

	public function __construct( $nodes = null ) {
		$this->nodes = $nodes;
	}

	public function appliesTo( $postId ) {
		return ( new \WPML_Cornerstone_Data_Settings() )->is_handling_post( $postId );
	}

	public function getElements( $postId ) {
		$data = $this->getData( $postId );

		if ( ! $data ) {
			return [];
		}

		$nodes    = $this->nodes ?: new \WPML_Cornerstone_Translatable_Nodes();
		$elements = [];

		$this->walk( $data, $nodes, $elements );

		return $elements;
	}

	public function getSource() {
		return Capture::SOURCE;
	}

	private function getData( $postId ) {
		if ( Utils::isLayoutPostType( get_post_type( $postId ) ) ) {
			$post = get_post( $postId );
			$raw  = $post instanceof \WP_Post ? $post->post_content : '';
		} else {
			$raw = get_post_meta( $postId, '_cornerstone_data', true );
		}

		$data = is_string( $raw ) ? json_decode( $raw, true ) : $raw;

		return is_array( $data ) ? $data : [];
	}

	private function walk( array $dataArray, $nodes, array &$elements ) {
		foreach ( $dataArray as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}

			if ( isset( $element['_type'] ) && ! Utils::typeIsLayout( $element['_type'] ) ) {
				if ( ! empty( $element['_id'] ) && is_scalar( $element['_id'] ) ) {
					$strings = $nodes->get( Utils::getNodeId( $element ), $element );

					if ( $strings ) {
						$elements[] = [
							'uid'     => (string) $element['_id'],
							'hash'    => Hash::of( $element ),
							'strings' => $strings,
						];
					}
				}

				if ( Utils::shouldCheckForSubmodules( $element['_type'] ) ) {
					$this->walk( $element, $nodes, $elements );
				}
			} else {
				$this->walk( $element, $nodes, $elements );
			}
		}
	}
}
