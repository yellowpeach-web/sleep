<?php

namespace WPML\TM\ATE\ClonedSites\Rebind;

class State {

	const OPTION = 'wpml_ate_project_rebind';

	public static function noteRegistrationAnswer( $body ) {
		if ( ! is_array( $body ) || empty( $body['reused_existing_website'] ) ) {
			return;
		}

		$uuid = isset( $body['website_uuid'] ) ? (string) $body['website_uuid'] : '';

		update_option( self::OPTION, [
			'website_uuid' => $uuid,
			'items'        => isset( $body['existing_translations_count'] )
				? max( 0, (int) $body['existing_translations_count'] )
				: null,
			'at'           => time(),
		], false );
	}

	public static function get() {
		$data = get_option( self::OPTION, null );

		return is_array( $data ) && ! empty( $data['website_uuid'] ) ? $data : null;
	}

	public static function websiteUuid() {
		$data = self::get();

		return $data ? (string) $data['website_uuid'] : '';
	}

	public static function items() {
		$data = self::get();

		if ( ! $data || ! isset( $data['items'] ) ) {
			return null;
		}

		return (int) $data['items'];
	}

	public static function clear() {
		delete_option( self::OPTION );
	}
}
