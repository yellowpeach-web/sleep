<?php

namespace ACFML\Field;

class ReferenceKeyLoadHooks implements \IWPML_Backend_Action, \IWPML_Frontend_Action {

	const REFERENCE_PREFIX = '_';
	const REFERENCE_START  = 'field_';

	public function add_hooks() {
		add_filter( 'acf/load_meta', [ $this, 'addMissingReferenceKeys' ], 10, 2 );
	}

	public function addMissingReferenceKeys( $meta, $postId ) {
		if ( ! is_array( $meta ) ) {
			return $meta;
		}

		$decoded = acf_decode_post_id( $postId );
		$id      = (int) $decoded['id'];
		if ( ! $id ) {
			return $meta;
		}

		if ( 'post' === $decoded['type'] ) {
			$originalId = $this->getOriginalPostId( $id );
		} elseif ( 'term' === $decoded['type'] ) {
			$originalId = $this->getOriginalTermId( $id );
		} else {
			return $meta;
		}

		if ( ! $originalId ) {
			return $meta;
		}

		return $this->addReferencesFromOriginal(
			$meta,
			(array) get_metadata( $decoded['type'], $id ),
			(array) get_metadata( $decoded['type'], $originalId )
		);
	}

	private function getOriginalPostId( int $postId ): int {
		$postType = get_post_type( $postId );
		if ( ! $postType ) {
			return 0;
		}

		$originalId = (int) apply_filters( 'wpml_original_element_id', null, $postId, 'post_' . $postType );

		return $originalId !== $postId ? $originalId : 0;
	}

	private function getOriginalTermId( int $termId ): int {
		$keepTermsInRequestedLanguage = function () {
			return true;
		};
		add_filter( 'wpml_disable_term_adjust_id', $keepTermsInRequestedLanguage );

		try {
			$originalId = 0;
			$term       = get_term( $termId );
			if ( $term instanceof \WP_Term ) {
				$originalTtId = (int) apply_filters( 'wpml_original_element_id', null, $term->term_taxonomy_id, 'tax_' . $term->taxonomy );
				if ( $originalTtId && $originalTtId !== (int) $term->term_taxonomy_id ) {
					$original   = get_term_by( 'term_taxonomy_id', $originalTtId, $term->taxonomy );
					$originalId = $original instanceof \WP_Term ? (int) $original->term_id : 0;
				}
			}

			return $originalId;
		} finally {
			remove_filter( 'wpml_disable_term_adjust_id', $keepTermsInRequestedLanguage );
		}
	}

	private function addReferencesFromOriginal( array $meta, array $translationMeta, array $originalMeta ) {
		foreach ( $translationMeta as $key => $values ) {
			$referenceKey = self::REFERENCE_PREFIX . $key;
			if ( isset( $meta[ $referenceKey ] ) || isset( $translationMeta[ $referenceKey ] ) ) {
				continue;
			}

			$reference = $originalMeta[ $referenceKey ][0] ?? null;
			if ( is_string( $reference ) && 0 === strpos( $reference, self::REFERENCE_START ) ) {
				$meta[ $key ]          = $this->unserialize( $values[0] ?? '' );
				$meta[ $referenceKey ] = $reference;
			}
		}

		return $meta;
	}

	private function unserialize( $value ) {
		return $this->isAcf61OrNewer() ? acf_maybe_unserialize( $value ) : maybe_unserialize( $value );
	}

	private function isAcf61OrNewer(): bool {
		return function_exists( 'acf_maybe_unserialize' );
	}
}
