<?php

class WPML_TM_Settings_Post_Process extends WPML_TM_User {

	public function run( array $previous ) {
		$settings = $this->tm_instance->settings;
		foreach (
			[
				WPML_POST_META_READONLY_SETTING_INDEX,
				WPML_TERM_META_READONLY_SETTING_INDEX,
				WPML_POST_TYPE_READONLY_SETTING_INDEX,
			] as $index
		) {
			$current = isset( $settings[ $index ] ) && is_array( $settings[ $index ] ) ? $settings[ $index ] : [];
			$before  = $previous[ $index ] ?? [];
			if ( array_diff( $current, $before ) || array_diff( $before, $current ) ) {
				$this->tm_instance->save_settings();

				return;
			}
		}
	}
}