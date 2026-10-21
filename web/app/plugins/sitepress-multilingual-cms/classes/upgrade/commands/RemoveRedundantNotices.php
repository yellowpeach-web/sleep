<?php

namespace WPML\Upgrade\Commands;

class RemoveRedundantNotices implements \IWPML_Upgrade_Command {

	const NOTICES = [
		[ 'default', 'WPML_Upgrade_Media_Duplication_In_Core' ],
		[ 'default', 'WPML_Upgrade_Display_Mode_For_Posts' ],
		[ 'wpml-tf-promote', 'notice-new-site' ],
		[ 'translation-service-instructions', 'translation-service-instructions' ],
	];

	public function run_admin() {
		$notices = wpml_get_admin_notices();

		foreach ( self::NOTICES as list( $group, $id ) ) {
			$notices->remove_notice( $group, $id );
		}

		return true;
	}

	public function run_ajax() {}

	public function run_frontend() {}

	public function get_results() {
		return true;
	}
}
