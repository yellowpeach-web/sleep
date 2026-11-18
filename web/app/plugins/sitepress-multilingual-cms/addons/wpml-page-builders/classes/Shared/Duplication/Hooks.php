<?php

namespace WPML\PB\Duplication;

use WPML\PB\Links\PostDataLinks;

class Hooks implements \IWPML_Action {

	const PRIORITY_REFRESH_META_CACHE_BEFORE_CONVERTERS = 9;

	private $dataSettings;

	private $postDataLinks;

	public function __construct( \IWPML_Page_Builders_Data_Settings $dataSettings, ?PostDataLinks $postDataLinks = null ) {
		$this->dataSettings  = $dataSettings;
		$this->postDataLinks = $postDataLinks ?: new PostDataLinks( $dataSettings );
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

		if ( $this->postDataLinks->convert( $duplicatePostId, $lang ) ) {
			do_action( 'wpml_pb_duplicate_links_converted', $duplicatePostId );
		}
	}
}
