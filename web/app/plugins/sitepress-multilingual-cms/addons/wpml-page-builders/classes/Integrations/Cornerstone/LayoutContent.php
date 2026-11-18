<?php

namespace WPML\PB\Cornerstone;

use WPML\PB\Helper\TranslationContent;

class LayoutContent {

	public static function update( array $postarr ) {
		if ( ! self::isKsesFilteringPostContent() ) {
			return TranslationContent::update( $postarr );
		}

		kses_remove_filters();

		try {
			return TranslationContent::update( $postarr );
		} finally {
			kses_init_filters();
		}
	}

	public static function isKsesFilteringPostContent() {
		return false !== has_filter( 'content_save_pre', 'wp_filter_post_kses' );
	}
}
