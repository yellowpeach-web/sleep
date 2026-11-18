<?php

namespace WPML\PB\Links;

use WPML\PB\Duplication\TargetLanguageLinks;

use function WPML\Container\make;

class PostDataLinks {

	private $dataSettings;

	private $dataStorage;

	public function __construct( \IWPML_Page_Builders_Data_Settings $dataSettings, ?DataStorage $dataStorage = null ) {
		$this->dataSettings = $dataSettings;
		$this->dataStorage  = $dataStorage ?: new PostMetaDataStorage( $dataSettings );
	}

	public function convert( $postId, $language ) {
		return ( new TargetLanguageLinks( make( \WPML_Translate_Link_Targets::class ) ) )->convert(
			$language,
			function ( \WPML_Translate_Link_Targets $translateLinkTargets ) use ( $postId ) {
				$hasChanged = false;

				foreach ( $this->dataSettings->get_fields_to_save() as $metaKey ) {
					$hasChanged = $this->convertField( $postId, $metaKey, $translateLinkTargets ) || $hasChanged;
				}

				return $hasChanged;
			}
		);
	}

	private function convertField( $postId, $metaKey, \WPML_Translate_Link_Targets $translateLinkTargets ) {
		$data = $this->dataStorage->get( $postId, $metaKey );

		if ( ! is_array( $data ) || ! $data ) {
			return false;
		}

		$hasChanged = false;
		$converted  = $this->convertValues( $data, $translateLinkTargets, $hasChanged );

		if ( $hasChanged ) {
			$this->dataStorage->save( $postId, $metaKey, $converted );
		}

		return $hasChanged;
	}

	private function convertValues( $values, \WPML_Translate_Link_Targets $translateLinkTargets, &$hasChanged ) {
		foreach ( $values as $key => $value ) {
			if ( is_array( $value ) || is_object( $value ) ) {
				$converted = $this->convertValues( $value, $translateLinkTargets, $hasChanged );
			} else {
				$converted = $this->convertString( $value, $translateLinkTargets );

				if ( $converted === $value ) {
					continue;
				}

				$hasChanged = true;
			}

			if ( is_object( $values ) ) {
				$values->$key = $converted;
			} else {
				$values[ $key ] = $converted;
			}
		}

		return $values;
	}

	private function convertString( $value, \WPML_Translate_Link_Targets $translateLinkTargets ) {
		if ( ! is_string( $value ) ) {
			return $value;
		}

		if ( \AbsoluteLinks::has_href_attribute( $value ) ) {
			return $translateLinkTargets->convert_text( $value );
		}

		if ( preg_match( '#^https?://#', $value ) ) {
			return $translateLinkTargets->convert_url( $value );
		}

		if ( preg_match( '#^/[^/\s][^\s]*$#', $value ) ) {
			return $this->convertRootRelativeUrl( $value, $translateLinkTargets );
		}

		return $value;
	}

	private function convertRootRelativeUrl( $path, \WPML_Translate_Link_Targets $translateLinkTargets ) {
		$url       = $this->getSiteOrigin() . $path;
		$converted = $translateLinkTargets->convert_url( $url );

		return $converted === $url ? $path : wp_make_link_relative( $converted );
	}

	private function getSiteOrigin() {
		$home   = (array) wp_parse_url( (string) get_option( 'home' ) );
		$scheme = $home['scheme'] ?? 'http';
		$host   = $home['host'] ?? '';
		$port   = isset( $home['port'] ) ? ':' . $home['port'] : '';

		return $scheme . '://' . $host . $port;
	}
}
