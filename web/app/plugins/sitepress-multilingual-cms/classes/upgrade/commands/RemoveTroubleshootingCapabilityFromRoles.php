<?php

namespace WPML\Upgrade\Commands;

class RemoveTroubleshootingCapabilityFromRoles extends \WPML_Upgrade_Run_All {

	const CAPABILITY = 'wpml_manage_troubleshooting';

	const REPLACEMENT_CAPABILITY = 'wpml_manage_support';

	protected function run() {
		$wp_roles = wp_roles();
		if ( ! $wp_roles ) {
			return true;
		}

		foreach ( array_keys( $wp_roles->roles ) as $role_name ) {
			$role = get_role( $role_name );
			if ( $role && $role->has_cap( self::CAPABILITY ) ) {
				if ( ! $role->has_cap( self::REPLACEMENT_CAPABILITY ) ) {
					$role->add_cap( self::REPLACEMENT_CAPABILITY );
				}
				$role->remove_cap( self::CAPABILITY );
			}
		}

		return true;
	}
}
