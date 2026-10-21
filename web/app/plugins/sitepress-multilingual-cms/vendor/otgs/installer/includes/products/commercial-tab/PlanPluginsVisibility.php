<?php

namespace OTGS\Installer\CommercialTab;

class PlanPluginsVisibility {

	const META_KEY     = 'otgs_installer_hide_plan_plugins';
	const AJAX_ACTION  = 'installer_hide_plan_plugins';
	const NONCE_ACTION = 'installer_hide_plan_plugins';
	const CAPABILITY   = 'install_plugins';

	public static function addHooks() {
		add_action( 'wp_ajax_' . self::AJAX_ACTION, self::class . '::save' );
	}

	public static function isHidden() {
		return (bool) get_user_meta( get_current_user_id(), self::META_KEY, true );
	}

	public static function save() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

		if ( ! current_user_can( self::CAPABILITY ) || ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			wp_send_json_error(
				[ 'error' => __( 'You are not allowed to change this setting.', 'installer' ) ],
				403
			);
			return;
		}

		$hidden = isset( $_POST['hidden'] ) && filter_var( wp_unslash( $_POST['hidden'] ), FILTER_VALIDATE_BOOLEAN );

		update_user_meta( get_current_user_id(), self::META_KEY, $hidden ? 1 : 0 );

		wp_send_json_success( [ 'hidden' => $hidden ] );
	}
}
