<?php

namespace WPML\PB\Elementor\Media\Modules;

class Video extends \WPML_Elementor_Media_Node {

	public function translate( $settings, $target_lang, $source_lang ) {
		foreach ( [ 'image_overlay', 'poster', 'hosted_url' ] as $property ) {
			$settings = $this->translate_image_property( $settings, $property, $target_lang, $source_lang );
		}

		return $settings;
	}
}
