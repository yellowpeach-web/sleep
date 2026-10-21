<?php

namespace WPML\MediaTranslation;

use WPML\Media\Option;
use WPML\TM\API\Jobs;

class ExcludeTranslatedMediaFromAutomaticJob implements \IWPML_Backend_Action, \IWPML_REST_Action {

	const SOURCE_SNAPSHOT_META_PREFIX = MediaSourceBaseline::META_PREFIX;

	private $sitepress;

	private $media_field;

	private $translation_ids = [];

	private $cached_language = null;

	public function __construct( \SitePress $sitepress, ?MediaField $media_field = null ) {
		$this->sitepress   = $sitepress;
		$this->media_field = $media_field ?: new MediaField();
	}

	public function add_hooks() {
		if ( Option::getTranslateMediaLibraryTexts() || Option::shouldHandleMediaAuto() ) {
			add_filter( 'wpml_translation_package_by_language', [ $this, 'excludeTranslatedMedia' ], 10, 4 );
		}
	}

	public function excludeTranslatedMedia( $package, $element, $lang, $sendFrom = null ) {
		if ( Jobs::SENT_AUTOMATICALLY !== (int) $sendFrom ) {
			return $package;
		}

		if ( ! is_array( $package ) || empty( $package['contents'] ) || ! is_array( $package['contents'] ) || ! $lang ) {
			return $package;
		}

		$this->resetCache( $lang );

		foreach ( $package['contents'] as $field => $data ) {
			if ( empty( $data['translate'] ) ) {
				continue;
			}

			$media = $this->media_field->extractAttachmentIdAndMediaFields( $field );
			if ( ! $media ) {
				continue;
			}

			if ( $this->isAlreadyTranslated( $media['attachment_id'], $media['media_field'], $data, $lang ) ) {
				$package['contents'][ $field ]['translate'] = 0;
			}
		}

		return $package;
	}

	private function resetCache( $lang ) {
		if ( $this->cached_language !== $lang ) {
			$this->translation_ids = [];
			$this->cached_language = $lang;
		}
	}

	private function isAlreadyTranslated( $attachment_id, $media_field, array $data, $lang ) {
		$translation_id = $this->getTranslationId( $attachment_id, $lang );
		if ( ! $translation_id ) {
			return false;
		}

		$translated = $this->readField( $translation_id, $media_field );
		if ( '' === trim( (string) $translated ) ) {
			return false;
		}

		$source = $this->decode( $data );

		$baseline = MediaSourceBaseline::read( $attachment_id, $media_field, $lang );

		if ( null !== $baseline ) {
			return $baseline === $source;
		}

		$already = $translated !== $source;

		if ( $already ) {
			MediaSourceBaseline::record( $attachment_id, $media_field, $lang, $source );
		}

		return $already;
	}

	private function getTranslationId( $attachment_id, $lang ) {
		if ( ! array_key_exists( $attachment_id, $this->translation_ids ) ) {
			$id = $this->sitepress->get_object_id( $attachment_id, 'attachment', false, $lang );

			$this->translation_ids[ $attachment_id ] = ( $id && (int) $id !== (int) $attachment_id ) ? (int) $id : null;
		}

		return $this->translation_ids[ $attachment_id ];
	}

	private function readField( $translation_id, $media_field ) {
		switch ( $media_field ) {
			case 'title':
				return get_post_field( 'post_title', $translation_id );
			case 'caption':
				return get_post_field( 'post_excerpt', $translation_id );
			case 'description':
				return get_post_field( 'post_content', $translation_id );
			case 'alt_text':
				return get_post_meta( $translation_id, '_wp_attachment_image_alt', true );
			default:
				return null;
		}
	}

	private function decode( array $data ) {
		$value = isset( $data['data'] ) ? $data['data'] : '';

		if ( isset( $data['format'] ) && 'base64' === $data['format'] ) {
			$value = base64_decode( $value );
		}

		return (string) $value;
	}
}
