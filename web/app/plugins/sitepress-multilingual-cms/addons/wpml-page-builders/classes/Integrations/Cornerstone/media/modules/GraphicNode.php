<?php

namespace WPML\PB\Cornerstone\Media\Modules;

class GraphicNode extends \WPML_Cornerstone_Media_Node_With_URLs {

	const IMAGE_KEYS = [ 'graphic_image_src', 'graphic_image_src_alt' ];

	const PREFIXES_BY_TYPE = [
		'headline'     => [ 'text' ],
		'button'       => [ 'anchor' ],
		'creative-cta' => [ 'anchor', 'anchor_interactive_content' ],
		'social'       => [ 'anchor' ],
		'testimonial'  => [ 'testimonial' ],
		'quote'        => [ 'quote_marks_opening', 'quote_marks_closing', 'quote_cite' ],
	];

	private $graphicPrefixes;

	public function __construct( \IWPML_PB_Media_Find_And_Translate $media_translate, array $graphicPrefixes ) {
		parent::__construct( $media_translate );
		$this->graphicPrefixes = $graphicPrefixes;
	}

	protected function get_keys() {
		return self::getImageKeys( $this->graphicPrefixes );
	}

	public static function getImageKeys( array $graphicPrefixes ): array {
		$keys = [];

		foreach ( $graphicPrefixes as $graphicPrefix ) {
			foreach ( self::IMAGE_KEYS as $imageKey ) {
				$keys[] = $graphicPrefix . '_' . $imageKey;
			}
		}

		return $keys;
	}
}
