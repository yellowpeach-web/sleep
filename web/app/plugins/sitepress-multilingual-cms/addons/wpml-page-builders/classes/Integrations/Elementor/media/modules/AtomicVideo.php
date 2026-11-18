<?php

namespace WPML\PB\Elementor\Media\Modules;

use WPML\FP\Obj;

class AtomicVideo extends \WPML_Elementor_Media_Node {

	const SOURCE_PATH = [ 'source', 'value' ];
	const POSTER_PATH = [ 'poster', 'value', 'src', 'value' ];
	const ID_PATH     = [ 'id', 'value' ];
	const URL_PATH    = [ 'url', 'value' ];

	public function translate( $settings, $target_lang, $source_lang ) {
		if ( ! is_array( $settings ) ) {
			return $settings;
		}

		$settings = $this->translateSourceAt( self::SOURCE_PATH, $settings, $target_lang, $source_lang );

		return $this->translateSourceAt( self::POSTER_PATH, $settings, $target_lang, $source_lang );
	}

	private function translateSourceAt( array $path, array $settings, $target_lang, $source_lang ) {
		$source = Obj::path( $path, $settings );

		if ( ! is_array( $source ) ) {
			return $settings;
		}

		$id = Obj::path( self::ID_PATH, $source );

		if ( is_numeric( $id ) && 0 < (int) $id ) {
			return Obj::assocPath(
				array_merge( $path, self::ID_PATH ),
				$this->media_translate->translate_id( (int) $id, $target_lang ),
				$settings
			);
		}

		$url = Obj::path( self::URL_PATH, $source );

		if ( is_string( $url ) && '' !== $url ) {
			return Obj::assocPath(
				array_merge( $path, self::URL_PATH ),
				$this->media_translate->translate_image_url( $url, $target_lang, $source_lang ),
				$settings
			);
		}

		return $settings;
	}
}
