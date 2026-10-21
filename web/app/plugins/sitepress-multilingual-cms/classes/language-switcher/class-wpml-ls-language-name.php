<?php

class WPML_LS_Language_Name {

	public static function compose( $native_name, $translated_name, $display_native, $display_translated ) {
		if ( $display_native && $display_translated && $native_name !== $translated_name ) {
			return $native_name . ' (' . $translated_name . ')';
		}

		if ( $display_native ) {
			return $native_name;
		}

		return $translated_name;
	}
}
