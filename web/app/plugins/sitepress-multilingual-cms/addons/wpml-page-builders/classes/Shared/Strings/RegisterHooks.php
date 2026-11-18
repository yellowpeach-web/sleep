<?php

namespace WPML\PB\Strings;

use WPML\PB\TranslationJob\Editor;

class RegisterHooks implements \IWPML_Backend_Action, \IWPML_Frontend_Action {

	public function add_hooks() {
		add_filter( 'wpml_elementor_widgets_to_translate', [ $this, 'changeVisualStringType' ], 1000 );
	}

	public function changeVisualStringType( array $nodes ) {
		if ( ! Editor::isATE() ) {
			return $nodes;
		}

		foreach ( $nodes as &$node ) {
			if ( isset( $node['fields'] ) && is_array( $node['fields'] ) ) {
				foreach ( $node['fields'] as &$field ) {
					if ( isset( $field['editor_type'] ) && 'VISUAL' === $field['editor_type'] ) {
						$field['editor_type'] = 'LINE';
					}
				}
			}
		}

		return $nodes;
	}

}
