<?php

namespace WPML\Media\TextScan;

class OutdatedMarker {

	const META = '_wpml_media_outdated';

	private $candidates;

	private $clock;

	public function __construct( Candidates $candidates, $clock = null ) {
		$this->candidates = $candidates;
		$this->clock      = $clock ?: function () {
			return microtime( true );
		};
	}

	public function markTranslations( $originalId, $originalSha1 ) {
		$originalId   = (int) $originalId;
		$originalFile = (string) get_post_meta( $originalId, '_wp_attached_file', true );
		$value        = null;
		$marked       = 0;

		foreach ( $this->candidates->translationsOf( $originalId ) as $translationId ) {
			$file = (string) get_post_meta( (int) $translationId, '_wp_attached_file', true );
			if ( '' === $file || $file === $originalFile ) {
				continue;
			}

			if ( null === $value ) {
				$value = wp_slash(
					wp_json_encode(
						[
							'at'            => (int) floor( call_user_func( $this->clock ) ),
							'original_sha1' => (string) $originalSha1,
						]
					)
				);
			}

			update_post_meta( (int) $translationId, self::META, $value );
			$marked++;
		}

		return $marked;
	}

	public function clear( $translationId ) {
		delete_post_meta( (int) $translationId, self::META );
	}

	public function isOutdated( $translationId ) {
		$value = get_post_meta( (int) $translationId, self::META, true );

		return null !== $value && false !== $value && '' !== $value;
	}
}
