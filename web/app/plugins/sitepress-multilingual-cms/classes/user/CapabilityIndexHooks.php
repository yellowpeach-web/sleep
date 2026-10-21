<?php

namespace WPML\User;

class CapabilityIndexHooks implements \IWPML_Backend_Action, \IWPML_Frontend_Action {

	public function add_hooks() {
		add_action( 'added_user_meta', [ $this, 'onCapabilitiesWritten' ], 10, 4 );
		add_action( 'updated_user_meta', [ $this, 'onCapabilitiesWritten' ], 10, 4 );
		add_action( 'deleted_user_meta', [ $this, 'onCapabilitiesDeleted' ], 10, 4 );
		add_action( 'deleted_user', [ $this, 'onUserDeleted' ] );
	}

	public function onCapabilitiesWritten( $meta_id, $user_id, $meta_key, $meta_value ) {
		$table_prefix = $this->prefixOfCapabilitiesKey( $meta_key );
		if ( null === $table_prefix ) {
			return;
		}

		CapabilityIndex::sync( (int) $user_id, $meta_value, $table_prefix );
	}

	public function onCapabilitiesDeleted( $meta_id, $user_id, $meta_key, $meta_value ) {
		$table_prefix = $this->prefixOfCapabilitiesKey( $meta_key );
		if ( null === $table_prefix ) {
			return;
		}

		CapabilityIndex::forget( (int) $user_id, $table_prefix );
	}

	public function onUserDeleted( $user_id ) {
		global $wpdb;

		CapabilityIndex::forget( (int) $user_id, $wpdb->prefix );
	}

	private function prefixOfCapabilitiesKey( $meta_key ) {
		global $wpdb;

		$suffix = 'capabilities';
		if ( ! is_string( $meta_key ) || substr( $meta_key, - strlen( $suffix ) ) !== $suffix ) {
			return null;
		}

		$prefix = substr( $meta_key, 0, - strlen( $suffix ) );
		if ( $prefix === $wpdb->base_prefix ) {
			return $prefix;
		}

		$blog_part = substr( $prefix, 0, strlen( $wpdb->base_prefix ) ) === $wpdb->base_prefix
			? substr( $prefix, strlen( $wpdb->base_prefix ) )
			: '';

		return preg_match( '/^[0-9]+_$/', $blog_part ) ? $prefix : null;
	}
}
