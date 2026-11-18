<?php

namespace WPML\PB\Elementor\Hooks;

class QueryWidgetFields implements \IWPML_Backend_Action, \IWPML_Frontend_Action {

	const WIDGET_POSTS = 'posts';

	const PRIORITY_AFTER_SERVED_CONFIG = 30;

	public function add_hooks() {
		add_filter( 'wpml_elementor_widgets_to_translate', [ $this, 'addMissingFields' ], self::PRIORITY_AFTER_SERVED_CONFIG );
	}

	public function addMissingFields( $widgets ) {
		if ( ! is_array( $widgets ) || ! isset( $widgets[ self::WIDGET_POSTS ] ) || ! is_array( $widgets[ self::WIDGET_POSTS ] ) ) {
			return $widgets;
		}

		$posts = $widgets[ self::WIDGET_POSTS ];

		$posts['fields'] = $this->appendMissing(
			isset( $posts['fields'] ) && is_array( $posts['fields'] ) ? $posts['fields'] : [],
			$this->getPostsFields()
		);

		$widgets[ self::WIDGET_POSTS ] = $posts;

		return $widgets;
	}

	private function appendMissing( array $existing, array $additions ) {
		$present = [];
		foreach ( $existing as $field ) {
			if ( isset( $field['field'] ) ) {
				$present[ $field['field'] ] = true;
			}
		}

		foreach ( $additions as $field ) {
			if ( ! isset( $present[ $field['field'] ] ) ) {
				$existing[]                 = $field;
				$present[ $field['field'] ] = true;
			}
		}

		return $existing;
	}

	private function line( $field, $label ) {
		return [
			'field'       => $field,
			'type'        => $label,
			'editor_type' => 'LINE',
		];
	}

	private function getPostsFields() {
		return [
			/* translators: Field label in WPML's translation editor for a page built with Elementor. Before the colon is the name Elementor gives the widget on its own canvas, after it the field inside that widget; keep both halves and the colon. */
			$this->line( 'classic_meta_separator', __( 'Posts: Meta separator', 'sitepress' ) ),
			/* translators: Field label in WPML's translation editor for a page built with Elementor. Before the colon is the name Elementor gives the widget on its own canvas, after it the field inside that widget; keep both halves and the colon. */
			$this->line( 'cards_meta_separator', __( 'Posts: Meta separator', 'sitepress' ) ),
		];
	}
}
