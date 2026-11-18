<?php

namespace WPML\PB\Elementor\Media\Modules;

use WPML\FP\Obj;

class ComponentOverrides extends \WPML_Elementor_Media_Node {

	const OVERRIDES_PATH      = [ 'component_instance', 'value', 'overrides', 'value' ];
	const OVERRIDE_VALUE_PATH = [ 'value', 'override_value' ];
	const TYPE_KEY            = '$$type';
	const TYPE_IMAGE          = 'image';
	const TYPE_VIDEO_SOURCE   = 'video-src';
	const TYPE_SVG_SOURCE     = 'svg-src';
	const IMAGE_SETTINGS_KEY  = 'image';
	const VIDEO_SETTINGS_KEY  = 'source';
	const SVG_SETTINGS_KEY    = 'svg';

	private $image;

	private $video;

	private $svg;

	public function __construct( \IWPML_PB_Media_Find_And_Translate $media_translate ) {
		parent::__construct( $media_translate );

		$this->image = new EImage( $media_translate );
		$this->video = new AtomicVideo( $media_translate );
		$this->svg   = new AtomicSvg( $media_translate );
	}

	public function translate( $settings, $target_lang, $source_lang ) {
		if ( ! is_array( $settings ) ) {
			return $settings;
		}

		$overrides = Obj::path( self::OVERRIDES_PATH, $settings );

		if ( ! is_array( $overrides ) ) {
			return $settings;
		}

		foreach ( $overrides as $index => $override ) {
			$value = is_array( $override ) ? Obj::path( self::OVERRIDE_VALUE_PATH, $override ) : null;

			if ( ! is_array( $value ) ) {
				continue;
			}

			$translated = $this->translateOverrideValue( $value, $target_lang, $source_lang );

			if ( $translated !== $value ) {
				$overrides[ $index ] = Obj::assocPath( self::OVERRIDE_VALUE_PATH, $translated, $override );
			}
		}

		return Obj::assocPath( self::OVERRIDES_PATH, $overrides, $settings );
	}

	private function translateOverrideValue( array $value, $target_lang, $source_lang ) {
		switch ( Obj::prop( self::TYPE_KEY, $value ) ) {
			case self::TYPE_IMAGE:
				return $this->translateAs( $this->image, self::IMAGE_SETTINGS_KEY, $value, $target_lang, $source_lang );

			case self::TYPE_VIDEO_SOURCE:
				return $this->translateAs( $this->video, self::VIDEO_SETTINGS_KEY, $value, $target_lang, $source_lang );

			case self::TYPE_SVG_SOURCE:
				return $this->translateAs( $this->svg, self::SVG_SETTINGS_KEY, $value, $target_lang, $source_lang );

			default:
				return $value;
		}
	}

	private function translateAs( \WPML_Elementor_Media_Node $module, $key, array $value, $target_lang, $source_lang ) {
		$settings = $module->translate( [ $key => $value ], $target_lang, $source_lang );

		return is_array( $settings ) && is_array( Obj::prop( $key, $settings ) ) ? $settings[ $key ] : $value;
	}
}
