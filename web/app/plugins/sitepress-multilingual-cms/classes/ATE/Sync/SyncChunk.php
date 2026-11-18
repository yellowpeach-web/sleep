<?php

namespace WPML\TM\ATE\Sync;

use WPML\TM\ATE\Handshake\Acceptance;

class SyncChunk {

	const OPTION = 'wpml_ate_sync_chunk';

	const DECLARED_DEFAULT = 200;

	const MIN = 10;
	const MAX = 500;

	public static function size() {
		$stored = self::stored();
		$size   = null === $stored ? self::defaultSize() : $stored;
		$size   = apply_filters( 'wpml_ate_sync_all_chunk', $size );

		return ( is_int( $size ) && $size >= 1 ) ? $size : Process::SYNC_ALL_CHUNK;
	}

	public static function defaultSize() {
		try {
			return Acceptance::accepted() ? self::DECLARED_DEFAULT : Process::SYNC_ALL_CHUNK;
		} catch ( \Throwable $e ) {
			return Process::SYNC_ALL_CHUNK;
		}
	}

	public static function stored() {
		$value = get_option( self::OPTION, null );

		return is_numeric( $value ) ? self::clamp( (int) $value ) : null;
	}

	public static function isDefault() {
		return null === self::stored();
	}

	public static function reset() {
		delete_option( self::OPTION );

		return [
			'ok'      => true,
			'chunk'   => self::size(),
			'default' => true,
			'clamped' => false,
		];
	}

	public static function save( $value ) {
		$value = is_string( $value ) ? trim( $value ) : $value;

		if ( ! is_numeric( $value ) ) {
			return [
				'ok'      => false,
				'chunk'   => self::size(),
				'default' => self::isDefault(),
				'clamped' => false,
			];
		}

		$asked   = (int) round( (float) $value );
		$clamped = self::clamp( $asked );

		update_option( self::OPTION, $clamped, true );

		return [
			'ok'      => true,
			'chunk'   => $clamped,
			'default' => false,
			'clamped' => $clamped !== $asked,
		];
	}

	public static function clamp( $value ) {
		return max( self::MIN, min( self::MAX, (int) $value ) );
	}
}
