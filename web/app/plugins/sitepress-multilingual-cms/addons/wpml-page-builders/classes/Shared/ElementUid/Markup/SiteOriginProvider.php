<?php

namespace WPML\PB\ElementUid\Markup;

use WPML\PB\ElementUid\Capture;
use WPML\PB\ElementUid\Hash;
use WPML\PB\SiteOrigin\DataSettings;
use WPML\PB\SiteOrigin\TranslatableNodes;

class SiteOriginProvider implements Provider {

	private $nodes;

	public function __construct( ?TranslatableNodes $nodes = null ) {
		$this->nodes = $nodes;
	}

	public function appliesTo( $postId ) {
		return ( new DataSettings() )->is_handling_post( $postId );
	}

	public function getElements( $postId ) {
		$data = get_post_meta( $postId, 'panels_data', true );

		if ( ! is_array( $data ) || empty( $data['widgets'] ) || ! is_array( $data['widgets'] ) ) {
			return [];
		}

		$nodes    = $this->nodes ?: new TranslatableNodes();
		$elements = [];

		$this->walk( $data['widgets'], $nodes, $elements );

		return $elements;
	}

	public function getSource() {
		return Capture::SOURCE;
	}

	private function walk( array $dataArray, TranslatableNodes $nodes, array &$elements ) {
		foreach ( $dataArray as $widget ) {
			if ( ! is_array( $widget ) ) {
				continue;
			}

			if ( isset( $widget[ TranslatableNodes::SETTINGS_FIELD ] ) ) {
				if ( TranslatableNodes::isWrappingModule( $widget ) ) {
					$this->walk( $widget[ TranslatableNodes::CHILDREN_FIELD ], $nodes, $elements );
					continue;
				}

				$uid = isset( $widget[ TranslatableNodes::SETTINGS_FIELD ]['widget_id'] )
					? (string) $widget[ TranslatableNodes::SETTINGS_FIELD ]['widget_id']
					: '';

				if ( '' === $uid ) {
					continue;
				}

				$strings = $nodes->get( $widget[ TranslatableNodes::SETTINGS_FIELD ]['class'], $widget );

				if ( $strings ) {
					$elements[] = [
						'uid'     => $uid,
						'hash'    => Hash::of( $widget ),
						'strings' => $strings,
					];
				}
			} elseif ( is_array( $widget ) ) {
				$this->walk( $widget, $nodes, $elements );
			}
		}
	}
}
