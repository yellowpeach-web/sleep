<?php

namespace WPML\TranslationPolicy;

class LatchGuard {

	public static function keep( $newTm, $oldTm ) {
		$key = FeatureSwitch::LATCH_KEY;
		if ( is_array( $newTm ) && empty( $newTm[ $key ] ) && self::holdsLatch( $oldTm ) ) {
			$newTm[ $key ] = $oldTm[ $key ];
		}

		return $newTm;
	}

	private static function holdsLatch( $tm ): bool {
		$latch = is_array( $tm ) ? ( $tm[ FeatureSwitch::LATCH_KEY ] ?? null ) : null;

		return is_string( $latch ) && '' !== $latch;
	}

	public static function onSettingsUpdate( $newValue, $oldValue ) {
		if ( is_array( $newValue ) && is_array( $oldValue ) && isset( $newValue['translation-management'], $oldValue['translation-management'] ) ) {
			$newValue['translation-management'] = self::keep( $newValue['translation-management'], $oldValue['translation-management'] );
		}

		return $newValue;
	}

	public static function addHooks(): void {
		add_filter( 'pre_update_option_icl_sitepress_settings', [ __CLASS__, 'onSettingsUpdate' ], 10, 2 );
	}
}
