<?php

namespace WPML\PB\Media;

class CssImages {

	const IMAGE_URL_PATTERN = '/url\(\s*([\'"]?)([^\'"()?\s]+)(?:\?id=(\d+))?\1\s*\)/';

	private $mediaTranslate;

	public function __construct( \IWPML_PB_Media_Find_And_Translate $mediaTranslate ) {
		$this->mediaTranslate = $mediaTranslate;
	}

	public function translate( string $css, string $targetLanguage, string $sourceLanguage, string $tagName = '' ): string {
		return (string) preg_replace_callback(
			self::IMAGE_URL_PATTERN,
			function ( array $matches ) use ( $targetLanguage, $sourceLanguage, $tagName ) {
				return $this->translateImageUrl( $matches, $targetLanguage, $sourceLanguage, $tagName );
			},
			$css
		);
	}

	public function findImageUrls( string $css ): array {
		return preg_match_all( self::IMAGE_URL_PATTERN, $css, $matches ) ? $matches[2] : [];
	}

	private function translateImageUrl( array $matches, string $targetLanguage, string $sourceLanguage, string $tagName ): string {
		$quote         = $matches[1];
		$translatedUrl = $this->mediaTranslate->translate_image_url( $matches[2], $targetLanguage, $sourceLanguage, $tagName );
		$attachmentId  = $matches[3] ?? '';

		$attachmentIdQuery = '' === $attachmentId
			? ''
			: '?id=' . $this->mediaTranslate->translate_id( (int) $attachmentId, $targetLanguage );

		return 'url(' . $quote . $translatedUrl . $attachmentIdQuery . $quote . ')';
	}
}
