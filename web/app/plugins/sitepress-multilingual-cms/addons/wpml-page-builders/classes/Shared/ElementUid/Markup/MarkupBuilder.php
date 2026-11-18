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
		$shared     = $this->getSharedFields( $postId, $provider, $elements );
		$wrappers   = [];

		foreach ( $elements as $element ) {
			$values = [];
			$keys   = isset( $shared[ $element['uid'] ] ) ? $this->getFieldKeys( $element['strings'] ) : [];

			foreach ( $element['strings'] as $index => $string ) {
				if ( isset( $keys[ $index ], $shared[ $element['uid'] ][ $keys[ $index ] ] ) ) {
					continue;
				}

				$values[] = trim( (string) $string->get_value() );
			}

			if ( ! $values ) {
				continue;
			}

			$attributes = ' data-wpml-uid="' . esc_attr( $element['uid'] ) . '"';

			if ( isset( $timestamps[ $element['uid'] ]['created'] ) ) {
				$attributes .= ' data-wpml-created="' . (int) $timestamps[ $element['uid'] ]['created'] . '"';
				$attributes .= ' data-wpml-modified="' . (int) $timestamps[ $element['uid'] ]['modified'] . '"';
			}

			$wrappers[] = '<div' . $attributes . '>' . "\n" . implode( "\n", $values ) . "\n" . '</div>';
		}

		return $wrappers ? implode( "\n", $wrappers ) : null;
	}

	private function getSharedFields( $postId, Provider $provider, array $elements ) {
		$originalId = (int) apply_filters( 'wpml_original_element_id', null, $postId, 'post_' . get_post_type( $postId ) );

		if ( $originalId > 0 && $originalId !== $postId ) {
			$originalProvider = $this->findProvider( $originalId );
			$elements         = $originalProvider ? $originalProvider->getElements( $originalId ) : [];
		}

		$listed = [];
		$shared = [];

		foreach ( $elements as $element ) {
			$keys = null;

			foreach ( $element['strings'] as $index => $string ) {
				$name = (string) $string->get_name();

				if ( ! isset( $listed[ $name ] ) ) {
					$listed[ $name ] = true;
					continue;
				}

				if ( null === $keys ) {
					$keys = $this->getFieldKeys( $element['strings'] );
				}

				$shared[ $element['uid'] ][ $keys[ $index ] ] = true;
			}
		}

		return $shared;
	}

	private function getFieldKeys( array $strings ) {
		$keys  = [];
		$count = [];

		foreach ( $strings as $index => $string ) {
			$title           = (string) $string->get_title();
			$count[ $title ] = isset( $count[ $title ] ) ? $count[ $title ] + 1 : 0;
			$keys[ $index ]  = $title . '#' . $count[ $title ];
		}

		return $keys;
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
