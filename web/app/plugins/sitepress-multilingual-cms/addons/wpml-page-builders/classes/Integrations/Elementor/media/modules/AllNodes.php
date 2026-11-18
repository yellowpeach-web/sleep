<?php


namespace WPML\PB\Elementor\Media\Modules;

use WPML\PB\Elementor\Helper\DynamicTag;

class AllNodes extends \WPML_Elementor_Media_Node {

	const DYNAMIC = '__dynamic__';

	const TAGS_WITH_FALLBACK_IMAGE = [ 'post-featured-image', 'acf-image', 'pods-image', 'toolset-image' ];

	const DEVICES = [ '', '_widescreen', '_laptop', '_tablet_extra', '_tablet', '_mobile_extra', '_mobile' ];

	private $backgroundKeys;

	private function getBackgroundKeys( $type ) {
		if ( ! isset( $this->backgroundKeys ) ) {
			$this->backgroundKeys = apply_filters(
				'wpml_elementor_media_backgrounds_to_translate',
				[
					'simple_fields'   => $this->getBackgroundImageFields(),
					'repeater_fields' => [
						'background_slideshow_gallery',
					],
				]
			);
		}

		return isset( $this->backgroundKeys[ $type ] ) && is_array( $this->backgroundKeys[ $type ] ) ? $this->backgroundKeys[ $type ] : [];
	}

	private function getBackgroundImageFields(): array {
		$prefixes = [
			'_background',
			'_background_hover',
			'background',
			'background_hover',
			'background_overlay',
			'background_overlay_hover',
			'overlay_background',
		];

		$imageFields = [];
		foreach ( $prefixes as $prefix ) {
			$imageFields[] = $prefix . '_image';
		}

		$fields   = self::getDeviceFields( $imageFields );
		$fields[] = '_background_video_fallback';
		$fields[] = 'background_video_fallback';

		return $fields;
	}

	public static function getDeviceFields( array $fields ): array {
		$deviceFields = [];
		foreach ( $fields as $field ) {
			foreach ( self::DEVICES as $device ) {
				$deviceFields[] = $field . $device;
			}
		}

		return $deviceFields;
	}

	public function translate( $settings, $target_lang, $source_lang ) {
		foreach ( $this->getBackgroundKeys( 'simple_fields' ) as $background ) {
			$settings = $this->translate_image_property( $settings, $background, $target_lang, $source_lang );
		}

		foreach ( $this->getBackgroundKeys( 'repeater_fields' ) as $background ) {
			$settings = $this->translate_images_property( $settings, $background, $target_lang, $source_lang );
		}

		foreach ( self::getDeviceFields( [ '_mask_image' ] ) as $field ) {
			$settings = $this->translate_image_property( $settings, $field, $target_lang, $source_lang );
		}

		return is_array( $settings ) ? $this->translateTagsAndIcons( $settings, $target_lang, $source_lang ) : $settings;
	}

	private function translateTagsAndIcons( array $settings, string $target_lang, string $source_lang ): array {
		$settings = $this->translateDynamicTags( $settings, $target_lang, $source_lang );
		$settings = $this->translateSvgIcons( $settings, $target_lang, $source_lang );

		foreach ( $settings as &$value ) {
			if ( $this->isRepeater( $value ) ) {
				foreach ( $value as &$item ) {
					$item = $this->translateDynamicTags( $item, $target_lang, $source_lang );
					$item = $this->translateSvgIcons( $item, $target_lang, $source_lang );
				}
				unset( $item );
			}
		}
		unset( $value );

		return $settings;
	}

	private function translateSvgIcons( array $settings, string $target_lang, string $source_lang ): array {
		foreach ( $settings as &$value ) {
			if ( is_array( $value ) && 'svg' === ( $value['library'] ?? null ) && is_array( $value['value'] ?? null ) ) {
				$value['value'] = $this->translate_image_array( $value['value'], $target_lang, $source_lang );
			}
		}
		unset( $value );

		return $settings;
	}

	private function translateDynamicTags( array $settings, string $target_lang, string $source_lang ): array {
		if ( ! isset( $settings[ self::DYNAMIC ] ) || ! is_array( $settings[ self::DYNAMIC ] ) ) {
			return $settings;
		}

		$translateImage = function ( $image ) use ( $target_lang, $source_lang ) {
			return is_array( $image ) ? $this->translate_image_array( $image, $target_lang, $source_lang ) : $image;
		};

		foreach ( $settings[ self::DYNAMIC ] as &$tag ) {
			if ( is_string( $tag ) ) {
				$tag = DynamicTag::mapSetting( $tag, [ 'lightbox' ], 'image', $translateImage );
				$tag = DynamicTag::mapSetting( $tag, self::TAGS_WITH_FALLBACK_IMAGE, 'fallback', $translateImage );
			}
		}
		unset( $tag );

		return $settings;
	}

	private function isRepeater( $value ): bool {
		if ( ! is_array( $value ) || ! $value ) {
			return false;
		}

		foreach ( $value as $key => $item ) {
			if ( ! is_int( $key ) || ! is_array( $item ) ) {
				return false;
			}
		}

		return true;
	}

}
