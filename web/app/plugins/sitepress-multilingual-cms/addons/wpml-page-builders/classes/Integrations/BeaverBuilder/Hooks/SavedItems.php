<?php

namespace WPML\PB\BeaverBuilder\Hooks;

class SavedItems implements \IWPML_Frontend_Action, \IWPML_Backend_Action {

	const POST_TYPE = 'fl-builder-template';

	const KINDS = [ 'row', 'column', 'module', 'template' ];

	public function add_hooks() {
		add_filter( 'fl_builder_node_settings', [ $this, 'translateSavedItems' ], 10, 2 );
	}

	public function translateSavedItems( $settings, $node = null ) {
		if ( ! is_object( $settings ) || empty( $settings->items ) || ! is_array( $settings->items ) ) {
			return $settings;
		}

		$items   = $settings->items;
		$changed = false;

		foreach ( $items as $index => $item ) {
			$translated = $this->translateItem( $item );

			if ( $translated !== $item ) {
				$items[ $index ] = $translated;
				$changed         = true;
			}
		}

		if ( ! $changed ) {
			return $settings;
		}

		$settings        = clone $settings;
		$settings->items = $items;

		return $settings;
	}

	private function translateItem( $item ) {
		$values = is_object( $item ) ? get_object_vars( $item ) : $item;

		if ( ! is_array( $values ) || ! in_array( $values['saved_layout'] ?? null, self::KINDS, true ) ) {
			return $item;
		}

		$field = 'saved_' . $values['saved_layout'];
		$id    = $values[ $field ] ?? null;

		if ( ! is_numeric( $id ) || (int) $id <= 0 ) {
			return $item;
		}

		$translatedId = (int) apply_filters( 'wpml_object_id', (int) $id, self::POST_TYPE, true );

		if ( ! $translatedId || $translatedId === (int) $id ) {
			return $item;
		}

		$value = is_int( $id ) ? $translatedId : (string) $translatedId;

		if ( is_object( $item ) ) {
			$item           = clone $item;
			$item->{$field} = $value;
		} else {
			$item[ $field ] = $value;
		}

		return $item;
	}
}
