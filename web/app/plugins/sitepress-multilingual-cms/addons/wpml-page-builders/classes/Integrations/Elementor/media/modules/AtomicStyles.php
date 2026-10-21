<?php

namespace WPML\PB\Elementor\Media\Modules;

use WPML\FP\Obj;
use WPML\PB\Elementor\V4\Hooks;

class AtomicStyles extends \WPML_Elementor_Media_Node {

	const IMAGE_SOURCE_TYPE = 'image-src';
	const ID_PATH           = [ 'value', 'id', 'value' ];
	const URL_PATH          = [ 'value', 'url', 'value' ];

	public function translate( $styles, $target_lang, $source_lang ) {
		if ( ! is_array( $styles ) ) {
			return $styles;
		}

		return $this->translateImageSources( $styles, $target_lang, $source_lang );
	}

	private function translateImageSources( array $props, $target_lang, $source_lang ) {
		if ( $this->isImageSource( $props ) ) {
			return $this->translateImageSource( $props, $target_lang, $source_lang );
		}

		foreach ( $props as $key => $value ) {
			if ( is_array( $value ) ) {
				$props[ $key ] = $this->translateImageSources( $value, $target_lang, $source_lang );
			}
		}

		return $props;
	}

	private function isImageSource( array $props ) {
		return self::IMAGE_SOURCE_TYPE === Obj::prop( Hooks::TYPE_KEY, $props )
			&& is_array( Obj::prop( 'value', $props ) );
	}

	private function translateImageSource( array $source, $target_lang, $source_lang ) {
		$id = Obj::path( self::ID_PATH, $source );

		if ( is_numeric( $id ) && 0 < (int) $id ) {
			return Obj::assocPath(
				self::ID_PATH,
				$this->media_translate->translate_id( (int) $id, $target_lang ),
				$source
			);
		}

		$url = Obj::path( self::URL_PATH, $source );

		if ( is_string( $url ) && '' !== $url ) {
			return Obj::assocPath(
				self::URL_PATH,
				$this->media_translate->translate_image_url( $url, $target_lang, $source_lang ),
				$source
			);
		}

		return $source;
	}
}
