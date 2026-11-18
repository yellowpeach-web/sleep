<?php

namespace ACFML\Cache;

use WPML\LIB\WP\Hooks;

class InternalPostKeyFlush implements \IWPML_Backend_Action, \IWPML_Frontend_Action, \IWPML_DIC_Action {

	const KINDS = [
		'post_type'       => 'acf-post-type',
		'taxonomy'        => 'acf-taxonomy',
		'ui_options_page' => 'acf-ui-options-page',
	];

	const PRIORITY = 1;

	public function add_hooks() {
		foreach ( self::KINDS as $hookName => $acfPostType ) {
			Hooks::onAction( 'acf/update_' . $hookName, self::PRIORITY )
				->then(
					function ( $args ) use ( $acfPostType ) {
						$this->flushKeyLookup( $acfPostType, is_array( $args ) ? reset( $args ) : null );
					}
				);
		}
	}

	public function flushKeyLookup( $acfPostType, $post ) {
		$key = is_array( $post ) && isset( $post['key'] ) ? $post['key'] : null;

		if ( ! $key || ! function_exists( 'acf_get_internal_post_type_instance' ) ) {
			return;
		}

		$instance = acf_get_internal_post_type_instance( $acfPostType );

		if ( ! is_object( $instance ) || empty( $instance->cache_key ) ) {
			return;
		}

		wp_cache_delete( $instance->cache_key . $key, 'acf' );
	}
}
