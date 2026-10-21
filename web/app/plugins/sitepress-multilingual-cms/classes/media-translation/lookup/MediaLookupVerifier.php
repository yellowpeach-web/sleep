<?php

namespace WPML\Media\Lookup;

class MediaLookupVerifier {

	public function verifyBatch( array $candidates ) {
		$ids = array_values( array_unique( array_map( function ( $candidate ) {
			return (int) $candidate['id'];
		}, $candidates ) ) );

		if ( $ids && function_exists( '_prime_post_caches' ) ) {
			_prime_post_caches( $ids, false, true );
		}

		return array_map( [ $this, 'verify' ], $candidates );
	}

	private function verify( $candidate ) {
		$post = get_post( (int) $candidate['id'] );

		if ( ! $post || 'attachment' !== $post->post_type ) {
			return false;
		}

		if ( MediaLookupTable::VARIANT_ATTACHED_FILE === (int) $candidate['variant'] ) {
			return get_post_meta( $post->ID, '_wp_attached_file', true ) === $candidate['value'];
		}

		return $post->guid === $candidate['value'];
	}
}
