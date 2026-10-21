<?php

namespace WPML\WPSEO\Shared;

use WPML\FP\Obj;

class HeadLangs {

	const NOTICE_ID = 'wpml-seo-disable-head-langs';

	const KEY_NOTICE_PENDING = 'disable_head_langs_notice_pending';

	public static function turnOff() {
		return self::save( false );
	}

	public static function turnBackOn() {
		if ( self::save( true ) ) {
			Options::set( self::KEY_NOTICE_PENDING, false );
			wpml_get_admin_notices()->remove_notice( 'default', self::NOTICE_ID );
		}
	}

	private static function save( $isOn ) {
		$seo = apply_filters( 'wpml_get_setting', [], 'seo' );
		if ( (bool) Obj::prop( 'head_langs', $seo ) === $isOn ) {
			return false;
		}

		$seo['head_langs'] = (int) $isOn;
		do_action( 'wpml_set_setting', 'seo', $seo, true );

		return true;
	}
}
