<?php

namespace WPML\LanguageEditor\Save\Phase;

final class CacheFlush {

	public static function afterColumnWrite() {
		if ( function_exists( 'icl_cache_clear' ) ) {
			icl_cache_clear();
		}

		do_action( 'icl_update_active_languages' );
		do_action( 'wpml_update_active_languages' );

		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}
	}
}
