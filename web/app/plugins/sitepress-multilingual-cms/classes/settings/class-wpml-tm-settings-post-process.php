<?php

use WPML\Core\Component\CustomFieldPreferences\Domain\ElementType;

class WPML_TM_Settings_Post_Process extends WPML_TM_User {

	public function run( array $previous ) {
		$without_maps = array_flip( ElementType::BLOB_KEYS );
		$current      = $this->tm_instance->settings;
		$current      = is_array( $current ) ? $current : [];

		if ( array_diff_key( $current, $without_maps ) !== array_diff_key( $previous, $without_maps ) ) {
			$this->tm_instance->save_settings();
		}
	}
}
