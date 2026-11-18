<?php

namespace WPML\PB\SiteOrigin\Media;

use WPML\FP\Obj;
use WPML\PB\SiteOrigin\TranslatableNodes;

class NodesIterator implements \IWPML_PB_Media_Nodes_Iterator {

	const IMAGE_WIDGET     = 'WP_Widget_Media_Image';
	const GALLERY_WIDGET   = 'WP_Widget_Media_Gallery';
	const ROWS             = 'grids';
	const CELLS            = 'grid_cells';
	const WIDGETS          = 'widgets';
	const WIDGET_INFO      = TranslatableNodes::SETTINGS_FIELD;
	const STYLE            = 'style';
	const BACKGROUND_IMAGE = 'background_image_attachment';
	const ATTACHMENT_ID    = 'attachment_id';
	const ATTACHMENT_URL   = 'url';
	const GALLERY_IDS      = 'ids';
	const NESTED_LAYOUT    = TranslatableNodes::CHILDREN_FIELD;

	private $mediaTranslate;

	public function __construct( \IWPML_PB_Media_Find_And_Translate $mediaTranslate ) {
		$this->mediaTranslate = $mediaTranslate;
	}

	public function translate( $panelsData, $language, $sourceLanguage ) {
		if ( ! is_array( $panelsData ) ) {
			return $panelsData;
		}

		foreach ( [ self::ROWS, self::CELLS ] as $layoutPart ) {
			foreach ( (array) Obj::propOr( [], $layoutPart, $panelsData ) as $index => $layoutItem ) {
				$panelsData[ $layoutPart ][ $index ] = $this->translateBackground( $layoutItem, $language );
			}
		}

		foreach ( (array) Obj::propOr( [], self::WIDGETS, $panelsData ) as $index => $widget ) {
			$panelsData[ self::WIDGETS ][ $index ] = $this->translateWidget( $widget, $language, $sourceLanguage );
		}

		return $panelsData;
	}

	private function translateWidget( $widget, $language, $sourceLanguage ) {
		if ( ! is_array( $widget ) ) {
			return $widget;
		}

		if ( isset( $widget[ self::WIDGET_INFO ] ) ) {
			$widget[ self::WIDGET_INFO ] = $this->translateBackground( $widget[ self::WIDGET_INFO ], $language );
		}

		switch ( Obj::path( [ self::WIDGET_INFO, 'class' ], $widget ) ) {
			case self::IMAGE_WIDGET:
				$widget = $this->translateImageWidget( $widget, $language, $sourceLanguage );
				break;
			case self::GALLERY_WIDGET:
				$widget = $this->translateGalleryWidget( $widget, $language );
				break;
		}

		if ( isset( $widget[ self::NESTED_LAYOUT ] ) ) {
			$widget[ self::NESTED_LAYOUT ] = $this->translate( $widget[ self::NESTED_LAYOUT ], $language, $sourceLanguage );
		}

		return $widget;
	}

	private function translateImageWidget( array $widget, $language, $sourceLanguage ) {
		$attachmentId = (int) Obj::propOr( 0, self::ATTACHMENT_ID, $widget );
		if ( ! $attachmentId ) {
			return $widget;
		}

		$translatedId = (int) $this->mediaTranslate->translate_id( $attachmentId, $language );
		if ( $translatedId === $attachmentId ) {
			return $widget;
		}

		$widget[ self::ATTACHMENT_ID ] = $translatedId;
		if ( ! empty( $widget[ self::ATTACHMENT_URL ] ) ) {
			$widget[ self::ATTACHMENT_URL ] = $this->mediaTranslate->translate_image_url( $widget[ self::ATTACHMENT_URL ], $language, $sourceLanguage );
		}

		return $widget;
	}

	private function translateGalleryWidget( array $widget, $language ) {
		if ( empty( $widget[ self::GALLERY_IDS ] ) || ! is_array( $widget[ self::GALLERY_IDS ] ) ) {
			return $widget;
		}

		$widget[ self::GALLERY_IDS ] = array_map(
			function ( $attachmentId ) use ( $language ) {
				return (int) $this->mediaTranslate->translate_id( (int) $attachmentId, $language );
			},
			$widget[ self::GALLERY_IDS ]
		);

		return $widget;
	}

	private function translateBackground( $item, $language ) {
		$attachmentId = (int) Obj::path( [ self::STYLE, self::BACKGROUND_IMAGE ], $item );
		if ( $attachmentId ) {
			$item[ self::STYLE ][ self::BACKGROUND_IMAGE ] = (int) $this->mediaTranslate->translate_id( $attachmentId, $language );
		}

		return $item;
	}

	public function get_media() {
		return $this->mediaTranslate->get_used_media_in_post();
	}
}
