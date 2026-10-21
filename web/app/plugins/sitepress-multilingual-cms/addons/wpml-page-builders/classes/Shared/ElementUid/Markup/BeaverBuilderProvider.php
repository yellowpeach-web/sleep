<?php

namespace WPML\PB\ElementUid\Markup;

use WPML\PB\BeaverBuilder\Component\Overrides;
use WPML\PB\ElementUid\Capture;
use WPML\PB\ElementUid\Hash;

class BeaverBuilderProvider implements Provider {

	private $nodes;

	public function __construct( $nodes = null ) {
		$this->nodes = $nodes;
	}

	public function appliesTo( $postId ) {
		return ( new \WPML_Beaver_Builder_Data_Settings() )->is_handling_post( $postId );
	}

	public function getElements( $postId ) {
		$data = get_post_meta( $postId, \WPML_Beaver_Builder_Data_Settings::META_FIELD_KEY, true );

		if ( ! is_array( $data ) ) {
			return [];
		}

		$nodes    = $this->nodes ?: new \WPML_Beaver_Builder_Translatable_Nodes();
		$elements = [];

		$this->walk( $data, $nodes, $elements );

		$allNodes = Overrides::collectNodes( $data );

		foreach ( $allNodes as $node ) {
			foreach ( Overrides::getOverrideTargets( $node, $allNodes ) as $target ) {
				$this->addElement( $elements, $target['node_id'], $target['settings'], $nodes );
			}
		}

		return $elements;
	}

	public function getSource() {
		return Capture::SOURCE;
	}

	private function walk( array $modules, $nodes, array &$elements ) {
		foreach ( \WPML_Beaver_Builder_Register_Strings::sort_modules( $modules ) as $node ) {
			if ( is_array( $node ) ) {
				$this->walk( $node, $nodes, $elements );
			} elseif ( is_object( $node )
				&& isset( $node->type, $node->node, $node->settings )
				&& 'module' === $node->type
				&& ! $this->isEmbeddedGlobalModule( $node )
			) {
				$this->addElement( $elements, (string) $node->node, $node->settings, $nodes );
			}
		}
	}

	private function addElement( array &$elements, $uid, $settings, $nodes ) {
		$strings = $nodes->get( $uid, $settings );

		if ( $strings ) {
			$elements[] = [
				'uid'     => $uid,
				'hash'    => Hash::of( $settings ),
				'strings' => $strings,
			];
		}
	}

	private function isEmbeddedGlobalModule( $node ) {
		return ! empty( $node->template_node_id ) && isset( $node->node ) && $node->template_node_id !== $node->node;
	}
}
