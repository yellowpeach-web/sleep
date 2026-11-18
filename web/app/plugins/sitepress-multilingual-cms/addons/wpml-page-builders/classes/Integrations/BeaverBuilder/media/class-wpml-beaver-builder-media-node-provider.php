<?php

use WPML\PB\BeaverBuilder\Media\Modules\AttachmentData;
use WPML\PB\BeaverBuilder\Media\Modules\AttachmentFields;
use WPML\PB\BeaverBuilder\Media\Modules\Composite;

class WPML_Beaver_Builder_Media_Node_Provider {

	private $media_translate;

	private $nodes = array();

	public function __construct( IWPML_PB_Media_Find_And_Translate $media_translate ) {
		$this->media_translate = $media_translate;
	}

	public function get( $type ) {
		if ( ! array_key_exists( $type, $this->nodes ) ) {
			switch ( $type ) {
				case 'photo':
					$node = new WPML_Beaver_Builder_Media_Node_Photo( $this->media_translate );
					break;

				case 'gallery':
					$node = new WPML_Beaver_Builder_Media_Node_Gallery( $this->media_translate );
					break;

				case 'content-slider':
					$node = new WPML_Beaver_Builder_Media_Node_Content_Slider( $this->media_translate );
					break;

				case 'slideshow':
					$node = new WPML_Beaver_Builder_Media_Node_Slideshow( $this->media_translate );
					break;

				case 'row':
					$node = new Composite(
						$this->media_translate,
						[
							$this->attachment_fields( [ 'bg_image', 'bg_parallax_image', 'bg_video_fallback', 'bg_video', 'bg_video_webm' ] ),
							new AttachmentData( $this->media_translate, [ 'bg_video_data', 'bg_video_webm_data' ] ),
							new WPML_Beaver_Builder_Media_Node_Slideshow( $this->media_translate, 'ss_photos', 'ss_photo_data' ),
						]
					);
					break;

				case 'column':
					$node = $this->attachment_fields( [ 'bg_image' ] );
					break;

				case 'callout':
					$node = $this->attachment_fields( [ 'photo' ] );
					break;

				case 'video':
					$node = new Composite(
						$this->media_translate,
						[
							$this->attachment_fields( [ 'video', 'video_webm', 'poster' ] ),
							new AttachmentData( $this->media_translate, [ 'data' ] ),
						]
					);
					break;

				case 'menu':
					$node = $this->attachment_fields( [ 'menu_logo_image' ] );
					break;

				case 'post-grid':
					$node = $this->attachment_fields( [ 'image_fallback' ] );
					break;

				default:
					$node = null;
			}

			$this->nodes[ $type ] = $node;
		}

		return $this->nodes[ $type ];
	}

	private function attachment_fields( array $field_names ) {
		return new AttachmentFields( $this->media_translate, $field_names );
	}

	public function get_media() {
		return $this->media_translate->get_used_media_in_post();
	}
}
