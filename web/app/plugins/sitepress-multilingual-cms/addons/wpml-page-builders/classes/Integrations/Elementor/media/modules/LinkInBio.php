<?php

namespace WPML\PB\Elementor\Media\Modules;

class LinkInBio extends \WPML_Elementor_Media_Node {

	public function translate( $settings, $target_lang, $source_lang ) {

		foreach ( [ 'identity_image', 'identity_image_cover' ] as $key ) {
			if ( isset( $settings[ $key ] ) ) {
				$settings[ $key ] = $this->convertImageArray( $settings[ $key ], $target_lang, $source_lang );
			}
		}

		foreach ( [ 'cta_link', 'image_links' ] as $key ) {
			if ( isset( $settings[ $key ] ) ) {
				$settings[ $key ] = $this->convertArrayOfImages( $settings[ $key ], $key . '_image', $target_lang, $source_lang );
			}
		}

		if ( isset( $settings['cta_link'] ) && is_array( $settings['cta_link'] ) ) {
			foreach ( $settings['cta_link'] as &$link ) {
				$link = $this->translate_image_property( $link, 'cta_link_file', $target_lang, $source_lang );
			}
			unset( $link );
		}

		$settings = $this->translate_image_property( $settings, 'cta_link_file', $target_lang, $source_lang );

		$backgrounds = AllNodes::getDeviceFields( [ 'background_border_background_group_image', 'background_border_background_overlay_group_image' ] );
		foreach ( $backgrounds as $field ) {
			$settings = $this->translate_image_property( $settings, $field, $target_lang, $source_lang );
		}

		return $settings;
	}

	private function convertImageArray( $image, $targetLang, $sourceLang ) {
		if ( isset( $image['id'] ) ) {
			$translatedId = $this->media_translate->translate_id( (int) $image['id'], $targetLang );
			
			if ( $translatedId !== (int) $image['id'] ) {
				$image['id'] = $translatedId;
				
				$image['url'] = $this->media_translate->translate_image_url(
					$image['url'],
					$targetLang,
					$sourceLang
				);
				
				$imageData    = wp_prepare_attachment_for_js( $translatedId );
				$image['alt'] = $imageData['alt'];
			}
		}

		return $image;
	}

	private function convertArrayOfImages( $images, $imageKey, $targetLang, $sourceLang ) {
		foreach ( $images as &$image ) {
			if ( isset( $image[ $imageKey ] ) ) {
				$image[ $imageKey ] = $this->convertImageArray( $image[ $imageKey ], $targetLang, $sourceLang );
			}
		}

		return $images;
	}
}
