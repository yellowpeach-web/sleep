<?php

namespace WPML\Compatibility\WPBakery;

class DesignOptionsImages {

	const ATTRIBUTE = 'css';

	const TAGS = [ 'vc_section', 'vc_row', 'vc_row_inner', 'vc_column', 'vc_column_inner' ];

	public static function addMediaSettings( array $mediaSettings ) {
		foreach ( self::TAGS as $tag ) {
			$mediaSettings[] = [
				'tag'        => [ 'name' => $tag ],
				'attributes' => [
					self::ATTRIBUTE => [ 'type' => \WPML_Page_Builders_Media_Shortcodes::TYPE_CSS ],
				],
			];
		}

		return $mediaSettings;
	}
}
