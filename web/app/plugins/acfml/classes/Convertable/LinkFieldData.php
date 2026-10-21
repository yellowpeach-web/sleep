<?php

namespace ACFML\Convertable;

class LinkFieldData extends AbstractUrlConvertable {

	public function convert( \WPML_ACF_Field $acf_field ) {
		$cameSerialized = is_serialized( $acf_field->meta_value );

		$dataUnpacked = (array) maybe_unserialize( $acf_field->meta_value );

		if ( isset( $dataUnpacked['url'] ) ) {
			$targetLang = $this->resolveTargetLang( $acf_field );
			$resolution = $this->resolveUrl( $dataUnpacked['url'], $targetLang );

			if ( self::RESOLUTION_STATE_DEFERRED === $resolution['resolution_state'] ) {
				return $acf_field->meta_value;
			}

			if ( $this->shouldResolveMissingPostIdsForTitle( $dataUnpacked, $resolution ) ) {
				$resolution = array_merge(
					$resolution,
					$this->resolvePostIdsLegacy( $dataUnpacked['url'], $targetLang )
				);
			}

			if ( $resolution['translated_post_id'] ) {
				$translatedPage = get_post( $resolution['translated_post_id'] );
			}

			if (
				isset( $translatedPage->post_title, $translatedPage->ID )
				&& $this->titleIsAutoFilled( $dataUnpacked, $resolution['source_post_id'] )
				&& $this->readerMaySee( $translatedPage )
			) {
				$dataUnpacked['title'] = $translatedPage->post_title;
			}

			$dataUnpacked['url'] = $resolution['url'];
		}

		if ( $cameSerialized ) {
			$dataUnpacked = maybe_serialize( $dataUnpacked );
		}

		return $dataUnpacked;
	}

	private function shouldResolveMissingPostIdsForTitle( array $dataUnpacked, array $resolution ) {
		return isset( $dataUnpacked['title'] )
			&& self::RESOLUTION_STATE_RESOLVED === $resolution['resolution_state']
			&& null === $resolution['source_post_id']
			&& null === $resolution['translated_post_id'];
	}

	private function titleIsAutoFilled( array $dataUnpacked, $sourcePostId ) {
		if ( ! isset( $dataUnpacked['title'] ) || ! $sourcePostId ) {
			return false;
		}

		return $this->normalizeTitle( $dataUnpacked['title'] )
			=== $this->normalizeTitle( get_post_field( 'post_title', $sourcePostId ) );
	}

	private function readerMaySee( $post ) {
		return is_post_status_viewable( get_post_status( $post ) )
			|| current_user_can( 'read_post', $post->ID );
	}

	private function normalizeTitle( $title ) {
		return trim( html_entity_decode( wp_strip_all_tags( (string) $title ), ENT_QUOTES ) );
	}
}
