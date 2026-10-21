<?php

namespace WPML\TM\ATE\TranslateEverything;

use WPML\Setup\Option;

class PendingOffer {

	const OPTION_PREFIX = 'wpml_tea_pending_offer_';

	const TTL = 3600;

	public static function record( array $types ) {
		if ( ! $types ) {
			return;
		}
		update_option(
			self::key(),
			array(
				'types'   => $types,
				'created' => time(),
			),
			false
		);
	}

	public static function get() {
		$record = get_option( self::key(), null );
		if ( ! is_array( $record ) || empty( $record['types'] ) || ! is_array( $record['types'] ) ) {
			return null;
		}

		return array(
			'types'   => $record['types'],
			'created' => isset( $record['created'] ) ? (int) $record['created'] : 0,
		);
	}

	public static function types() {
		$record = self::get();

		return $record ? $record['types'] : array();
	}

	public static function clear() {
		delete_option( self::key() );
	}

	public static function commit() {
		$record = self::get();
		if ( ! $record ) {
			return array();
		}

		if ( self::isExpired( $record ) ) {
			self::revert();

			return array();
		}

		$types = $record['types'];

		self::clear();

		$offered_modes = array();
		foreach ( $types as $slug => $modes ) {
			$offered_modes[ $slug ] = (int) $modes['new'];
		}

		$settings_helper = wpml_load_settings_helper();
		$settings_helper->update_cpt_sync_settings( $offered_modes, false );

		return array_keys( $types );
	}

	public static function revert() {
		$types = self::types();
		if ( ! $types ) {
			return array();
		}

		self::clear();

		$previous_modes = array();
		foreach ( $types as $slug => $modes ) {
			$previous_modes[ $slug ] = isset( $modes['old'] ) && null !== $modes['old']
				? (int) $modes['old']
				: WPML_CONTENT_TYPE_DONT_TRANSLATE;
		}

		$settings_helper = wpml_load_settings_helper();
		$settings_helper->update_cpt_sync_settings( $previous_modes, false );

		$slugs = array_keys( $types );

		PostTypesSinceRepositoryFactory::create()->discard( $slugs );

		CompletedMap::update(
			Option::TRANSLATE_EVERYTHING_POSTS,
			function ( array $completed ) use ( $slugs ) {
				foreach ( $slugs as $slug ) {
					unset( $completed[ $slug ] );
				}

				return $completed;
			}
		);

		return $slugs;
	}

	public static function revertIfExpired() {
		if ( ! get_current_user_id() ) {
			return;
		}
		$record = self::get();
		if ( $record && self::isExpired( $record ) ) {
			self::revert();
		}
	}

	public static function isExpired( array $record ) {
		return ( time() - (int) $record['created'] ) > self::TTL;
	}

	private static function key() {
		return self::OPTION_PREFIX . get_current_user_id();
	}
}
