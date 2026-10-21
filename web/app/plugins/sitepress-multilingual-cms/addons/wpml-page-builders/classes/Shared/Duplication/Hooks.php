<?php

namespace WPML\PB\Duplication;

use function WPML\Container\make;

class Hooks implements \IWPML_Action {

	const PRIORITY_REFRESH_META_CACHE_BEFORE_CONVERTERS = 9;

	private $dataSettings;

	public function __construct( \IWPML_Page_Builders_Data_Settings $dataSettings ) {
		$this->dataSettings = $dataSettings;
	}

	public function add_hooks() {
		add_action( 'icl_make_duplicate', [ self::class, 'refreshMetaCache' ], self::PRIORITY_REFRESH_META_CACHE_BEFORE_CONVERTERS, 4 );
		add_action( 'icl_make_duplicate', [ $this, 'convertLinks' ], 10, 4 );
	}

	public static function refreshMetaCache( $masterPostId, $lang, $postArray, $duplicatePostId ) {
		wp_cache_delete( $duplicatePostId, 'post_meta' );
	}

	public function convertLinks( $masterPostId, $lang, $postArray, $duplicatePostId ) {
		if ( ! $this->dataSettings->is_handling_post( $duplicatePostId ) ) {
			return;
		}

		do_action( 'wpml_switch_language', $lang );
		add_filter( 'wpml_force_translated_permalink', '__return_true' );

		$changed = false;
		try {
			$changed = $this->convertForBuilder( $duplicatePostId, make( \WPML_Translate_Link_Targets::class ) );
		} finally {
			remove_filter( 'wpml_force_translated_permalink', '__return_true' );
			do_action( 'wpml_switch_language', null );
		}

		if ( $changed ) {
			do_action( 'wpml_pb_duplicate_links_converted', $duplicatePostId );
		}
	}

	private function convertForBuilder( $duplicatePostId, \WPML_Translate_Link_Targets $translateLinkTargets ) {
		$data = $this->dataSettings->convert_data_to_array(
			get_post_meta( $duplicatePostId, $this->dataSettings->get_meta_field(), true )
		);

		if ( ! is_array( $data ) || ! $data ) {
			return false;
		}

		$changed   = false;
		$converted = $this->convertLinksInData( $data, $translateLinkTargets, $changed );

		if ( $changed ) {
			update_post_meta(
				$duplicatePostId,
				$this->dataSettings->get_meta_field(),
				$this->dataSettings->prepare_data_for_saving( $converted )
			);
		}

		return $changed;
	}

	private function convertLinksInData( $data, \WPML_Translate_Link_Targets $translateLinkTargets, &$changed ) {
		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) || is_object( $value ) ) {
				$value = $this->convertLinksInData( $value, $translateLinkTargets, $changed );
			} elseif ( is_string( $value ) ) {
				$converted = $this->convertString( $value, $translateLinkTargets );
				if ( $converted === $value ) {
					continue;
				}
				$value   = $converted;
				$changed = true;
			} else {
				continue;
			}

			if ( is_object( $data ) ) {
				$data->$key = $value;
			} else {
				$data[ $key ] = $value;
			}
		}

		return $data;
	}

	private function convertString( $value, \WPML_Translate_Link_Targets $translateLinkTargets ) {
		if ( \AbsoluteLinks::has_href_attribute( $value ) ) {
			return $translateLinkTargets->convert_text( $value );
		}

		if ( preg_match( '#^https?://#', $value ) ) {
			return $translateLinkTargets->convert_url( $value );
		}

		return $value;
	}
}
