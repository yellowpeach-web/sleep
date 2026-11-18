<?php

namespace WPML\PB\Elementor\Media\Modules;

class VideoPlaylist extends \WPML_Elementor_Media_Node {

	const TAB_FIELDS = [ 'thumbnail', 'hosted_url' ];

	public function translate( $settings, $target_lang, $source_lang ) {
		$settings = $this->translate_image_property( $settings, 'image_overlay', $target_lang, $source_lang );

		if ( ! isset( $settings['tabs'] ) || ! is_array( $settings['tabs'] ) ) {
			return $settings;
		}

		foreach ( $settings['tabs'] as &$tab ) {
			foreach ( self::TAB_FIELDS as $field ) {
				$tab = $this->translate_image_property( $tab, $field, $target_lang, $source_lang );
			}
		}
		unset( $tab );

		return $settings;
	}
}
