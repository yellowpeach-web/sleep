<?php

class WPML_WP_Cache_Factory {

	public function create_cache_group( $group ) {
		return new WPML_WP_Cache( $group );
	}

	public function create_cache_item( $group, $key ) {
		return new WPML_WP_Cache_Item( $this->create_cache_group( $group ), $key );
	}

	public function create_language_aware_cache_item( $group, array $key_parts ) {
		$key_parts[] = apply_filters( 'wpml_current_language', null );

		return $this->create_cache_item( $group, $key_parts );
	}

}
