<?php

namespace WPML\PB\SiteOrigin\Hooks;

use WPML\PB\Duplication\Hooks as DuplicationHooks;
use WPML\PB\SiteOrigin\DataSettings;

class Duplication implements \IWPML_Action {

	const PRIORITY_BEFORE_LINK_CONVERSION = DuplicationHooks::PRIORITY_REFRESH_META_CACHE_BEFORE_CONVERTERS - 1;

	private $dataSettings;

	public function __construct( DataSettings $dataSettings ) {
		$this->dataSettings = $dataSettings;
	}

	public function add_hooks() {
		add_action( 'icl_make_duplicate', [ $this, 'copyLayoutToDuplicate' ], self::PRIORITY_BEFORE_LINK_CONVERSION, 4 );
	}

	public function copyLayoutToDuplicate( $originalPostId, $language, $postArray, $duplicatePostId ) {
		$layout = get_post_meta( $originalPostId, $this->dataSettings->get_meta_field(), true );

		if ( ! is_array( $layout ) || ! $layout ) {
			return;
		}

		update_post_meta(
			$duplicatePostId,
			$this->dataSettings->get_meta_field(),
			$this->dataSettings->prepare_data_for_saving( $layout )
		);
	}
}
