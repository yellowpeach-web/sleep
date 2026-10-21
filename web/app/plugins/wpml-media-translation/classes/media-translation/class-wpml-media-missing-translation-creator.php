<?php

class WPML_Media_Missing_Translation_Creator {

	private $sitepress;

	private $media_parser_factory;

	private $image_translator;

	private $duplication_provider;

	public function __construct(
		SitePress $sitepress,
		\WPML\Media\Factories\WPML_Media_Element_Parser_Factory $media_parser_factory,
		WPML_Media_Image_Translate $image_translator,
		$duplication_provider
	) {
		$this->sitepress            = $sitepress;
		$this->media_parser_factory = $media_parser_factory;
		$this->image_translator     = $image_translator;
		$this->duplication_provider = $duplication_provider;
	}

	public function create_for_text( $text, $target_language, $source_language = null ) {
		if ( ! is_string( $text ) || '' === $text || ! $target_language ) {
			return 0;
		}

		if ( \WPML\Media\TranslationPauseScope::creationPaused( $target_language ) ) {
			return 0;
		}

		$created = 0;
		foreach ( $this->collect_attachment_ids( $text, $source_language ) as $attachment_id ) {
			if ( $this->create_missing_translation( $attachment_id, $target_language ) ) {
				$created ++;
			}
		}

		return $created;
	}

	private function collect_attachment_ids( $text, $source_language ) {
		$attachment_ids = [];

		foreach ( $this->media_parser_factory->create( $text ) as $media_parser ) {
			foreach ( $media_parser->getMediaElements() as $media ) {
				$media_src = $media_parser->getMediaSrcFromAttributes( $media['attributes'] );
				if ( ! $media_src ) {
					continue;
				}

				$attachment_id = (int) $this->image_translator->get_attachment_id_by_url( $media_src, $source_language );
				if ( $attachment_id && ! in_array( $attachment_id, $attachment_ids, true ) ) {
					$attachment_ids[] = $attachment_id;
				}
			}
		}

		return $attachment_ids;
	}

	private function create_missing_translation( $attachment_id, $target_language ) {
		$attachment_language = $this->sitepress->get_language_for_element( $attachment_id, 'post_attachment' );

		if ( ! $attachment_language || $attachment_language === $target_language ) {
			return false;
		}

		if ( icl_object_id( $attachment_id, 'attachment', false, $target_language ) ) {
			return false;
		}

		$duplication = call_user_func( $this->duplication_provider );
		if ( ! $duplication instanceof WPML_Media_Attachments_Duplication ) {
			return false;
		}

		$attachment = get_post( $attachment_id );

		return (bool) $duplication->create_duplicate_attachment(
			$attachment_id,
			$attachment ? $attachment->post_parent : false,
			$target_language
		);
	}
}
