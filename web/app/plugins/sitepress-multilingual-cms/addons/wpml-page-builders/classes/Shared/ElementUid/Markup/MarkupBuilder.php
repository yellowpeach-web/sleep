<?php

namespace WPML\PB\ElementUid\Markup;

use WPML\PB\ElementUid\Registry;

class MarkupBuilder {

	private $registry;

	private $providers;

	public function __construct( Registry $registry, array $providers ) {
		$this->registry  = $registry;
		$this->providers = $providers;
	}

	public function getForFilter( $markup, $postId ) {
		if ( null !== $markup ) {
			return $markup;
		}

		return $this->get( (int) $postId );
	}

	public function get( $postId ) {
		$provider = $this->findProvider( $postId );
		$elements = $provider ? $provider->getElements( $postId ) : [];

		if ( ! $elements ) {
			return null;
		}

		if ( $this->isTranslation( $postId ) ) {
			$this->updateRegistry( $postId, $elements, $provider->getSource() );
		}

		$timestamps = $this->registry->getTimestamps( $postId );
		$wrappers   = [];

		foreach ( $elements as $element ) {
			$attributes = ' data-wpml-uid="' . esc_attr( $element['uid'] ) . '"';

			if ( isset( $timestamps[ $element['uid'] ]['created'] ) ) {
				$attributes .= ' data-wpml-created="' . (int) $timestamps[ $element['uid'] ]['created'] . '"';
				$attributes .= ' data-wpml-modified="' . (int) $timestamps[ $element['uid'] ]['modified'] . '"';
			}

			$values = [];

			foreach ( $element['strings'] as $string ) {
				$values[] = trim( (string) $string->get_value() );
			}

			$wrappers[] = '<div' . $attributes . '>' . "\n" . implode( "\n", $values ) . "\n" . '</div>';
		}

		return implode( "\n", $wrappers );
	}

	public function refreshRegistry( $postId, $time = null ) {
		$postId = (int) $postId;

		if ( ! $this->isTranslation( $postId ) ) {
			return;
		}

		$provider = $this->findProvider( $postId );

		if ( $provider ) {
			$this->updateRegistry( $postId, $provider->getElements( $postId ), $provider->getSource(), $time );
		}
	}

	private function findProvider( $postId ) {
		if ( $postId <= 0 ) {
			return null;
		}

		foreach ( $this->providers as $provider ) {
			if ( $provider->appliesTo( $postId ) ) {
				return $provider;
			}
		}

		return null;
	}

	private function updateRegistry( $postId, array $elements, $source, $time = null ) {
		$hashes = [];

		foreach ( $elements as $element ) {
			if ( ! isset( $hashes[ $element['uid'] ] ) ) {
				$hashes[ $element['uid'] ] = $element['hash'];
			}
		}

		$this->registry->update( $postId, $hashes, $source, $time );
	}

	private function isTranslation( $postId ) {
		$details = apply_filters(
			'wpml_element_language_details',
			null,
			[
				'element_id'   => $postId,
				'element_type' => 'post_' . get_post_type( $postId ),
			]
		);

		return $details && ! empty( $details->source_language_code );
	}
}
