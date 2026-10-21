<?php

namespace WPML\TM\TranslationProxy;

class TpBatchState {

	const OPTION = 'wpml_tp_batch_state';

	private static $state;

	private static function get() {
		if ( ! isset( self::$state ) ) {
			self::$state = get_option( self::OPTION, [] );
		}

		return self::$state;
	}

	private static function save( array $state ) {
		self::$state = $state;
		update_option( self::OPTION, $state, false );
	}

	public static function setRemoteTargetLanguages( $remoteTargetLanguages ) {
		$state                            = self::get();
		$state['remote_target_languages'] = $remoteTargetLanguages;
		self::save( $state );
	}

	public static function getRemoteTargetLanguages() {
		$state = self::get();

		return isset( $state['remote_target_languages'] ) ? $state['remote_target_languages'] : false;
	}

	public static function setBatchData( $batch ) {
		$state          = self::get();
		$state['batch'] = $batch;
		self::save( $state );
	}

	public static function getBatchData() {
		$state = self::get();

		return isset( $state['batch'] ) ? $state['batch'] : false;
	}

	public static function getBatchDataForName( $batchName ) {
		$pinnedName = self::getBatchName();

		if ( ! $pinnedName || (string) $pinnedName !== (string) $batchName ) {
			return false;
		}

		return self::getBatchData();
	}

	public static function setBatchName( $batchName ) {
		$state         = self::get();
		$state['name'] = $batchName;
		self::save( $state );
	}

	public static function getBatchName() {
		$state = self::get();

		return isset( $state['name'] ) ? $state['name'] : false;
	}

	public static function clear() {
		$state = self::get();
		unset( $state['name'], $state['batch'], $state['remote_target_languages'] );
		self::save( $state );
	}
}
