<?php

use WPML\Core\Component\SettingsStorage\Application\Repository\StorageQueryInterface;

class WPML_Settings_Stored_Copy {

	public function registeredKeys() {
		$registry = get_option( StorageQueryInterface::REGISTRY_ROW, false );
		if ( ! is_array( $registry ) || ! isset( $registry['keys'] ) || ! is_array( $registry['keys'] ) ) {
			return [];
		}

		return array_map( 'strval', array_values( $registry['keys'] ) );
	}
}
