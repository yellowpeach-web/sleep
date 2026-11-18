<?php

namespace WPML\PB\Elementor\Media\Modules;

class WpWidgetMediaFile extends \WPML_Elementor_Media_Node {

	public function translate( $settings, $target_lang, $source_lang ) {
		$id = (int) ( $settings['wp']['attachment_id'] ?? 0 );
		if ( ! $id ) {
			return $settings;
		}

		$translatedId = $this->media_translate->translate_id( $id, $target_lang );

		if ( $translatedId !== $id ) {
			$settings['wp']['attachment_id'] = $translatedId;

			if ( ! empty( $settings['wp']['url'] ) ) {
				$settings['wp']['url'] = $this->media_translate->translate_image_url( $settings['wp']['url'], $target_lang, $source_lang );
			}
		}

		return $settings;
	}
}
