<?php

class WPML_Cornerstone_Media_Node_Provider {

	private $media_translate;

	private $nodes = array();

	private $background;

	public function __construct( IWPML_PB_Media_Find_And_Translate $media_translate ) {
		$this->media_translate = $media_translate;
	}

	public function get( $type ) {
		if ( ! array_key_exists( $type, $this->nodes ) ) {
			$this->add( $type );
		}

		return $this->nodes[ $type ];
	}

	private function add( $type ) {
		switch ( $type ) {
			case 'image':
				$node = new WPML_Cornerstone_Media_Node_Image( $this->media_translate );
				break;

			case 'card':
				$node = new \WPML\PB\Cornerstone\Media\Modules\CardNode( $this->media_translate );
				break;

			case 'video':
			case 'audio':
				$node = new \WPML\PB\Cornerstone\Media\Modules\MediaPlayerNode( $this->media_translate );
				break;

			case 'headline':
			case 'button':
			case 'creative-cta':
			case 'social':
			case 'testimonial':
			case 'quote':
				$node = new \WPML\PB\Cornerstone\Media\Modules\GraphicNode( $this->media_translate, \WPML\PB\Cornerstone\Media\Modules\GraphicNode::PREFIXES_BY_TYPE[ $type ] );
				break;

			case 'classic:creative-cta':
				$node = new WPML_Cornerstone_Media_Node_Classic_Creative_CTA( $this->media_translate );
				break;

			case 'classic:feature-box':
				$node = new WPML_Cornerstone_Media_Node_Classic_Feature_Box( $this->media_translate );
				break;

			case 'classic:card':
				$node = new WPML_Cornerstone_Media_Node_Classic_Card( $this->media_translate );
				break;

			case 'classic:image':
				$node = new WPML_Cornerstone_Media_Node_Classic_Image( $this->media_translate );
				break;

			case 'classic:promo':
				$node = new WPML_Cornerstone_Media_Node_Classic_Promo( $this->media_translate );
				break;

			default:
				$node = null;
		}

		$this->nodes[ $type ] = $node;
	}

	public function getBackground() {
		if ( null === $this->background ) {
			$this->background = new \WPML\PB\Cornerstone\Media\Modules\BackgroundNode( $this->media_translate );
		}

		return $this->background;
	}

	public function get_media() {
		return $this->media_translate->get_used_media_in_post();
	}
}
