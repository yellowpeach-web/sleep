<?php

spl_autoload_register(
	static function ( $class ) {
		$prefix = 'WPML\\ST\\Vendor\\Peast\\';

		if ( 0 !== strncmp( $class, $prefix, strlen( $prefix ) ) ) {
			return;
		}

		$file = __DIR__ . '/peast/lib/Peast/' . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';

		if ( is_file( $file ) ) {
			require $file;
		}
	}
);
