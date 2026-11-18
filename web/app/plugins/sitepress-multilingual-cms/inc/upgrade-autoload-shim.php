<?php

if ( ! function_exists( 'wpml_register_upgrade_autoload_shim' ) ) {
	function wpml_register_upgrade_autoload_shim() {
		static $registered = false;
		if ( $registered ) {
			return;
		}
		$registered = true;

		$vendor       = dirname( __DIR__ ) . '/vendor/composer';
		$classmapFile = $vendor . '/autoload_classmap.php';
		$psr4File     = $vendor . '/autoload_psr4.php';

		spl_autoload_register(
			function ( $class ) use ( $classmapFile, $psr4File ) {
				wpml_upgrade_autoload_fallback( $class, $classmapFile, $psr4File );
			},
			true,
			false
		);
	}
}

if ( ! function_exists( 'wpml_upgrade_autoload_fallback' ) ) {
	function wpml_upgrade_autoload_fallback( $class, $classmapFile, $psr4File ) {
		static $read = array();

		clearstatcache( true, $classmapFile );
		$stat = @stat( $classmapFile );
		if ( false === $stat ) {
			return;
		}
		$signature = $stat['ino'] . ':' . $stat['mtime'] . ':' . $stat['size'];

		if ( ! isset( $read[ $classmapFile ] ) || $read[ $classmapFile ]['signature'] !== $signature ) {
			if ( ! is_readable( $classmapFile ) || ! is_readable( $psr4File ) ) {
				return;
			}
			$classmap = require $classmapFile;
			$psr4     = require $psr4File;
			if ( ! is_array( $classmap ) || ! is_array( $psr4 ) ) {
				return;
			}
			$read[ $classmapFile ] = array(
				'signature' => $signature,
				'classmap'  => $classmap,
				'psr4'      => $psr4,
			);
		}

		wpml_upgrade_autoload_resolve( $class, $read[ $classmapFile ]['classmap'], $read[ $classmapFile ]['psr4'] );
	}
}

if ( ! function_exists( 'wpml_upgrade_autoload_resolve' ) ) {
	function wpml_upgrade_autoload_resolve( $class, array $classmap, array $psr4 ) {
		if ( class_exists( $class, false ) || interface_exists( $class, false ) || trait_exists( $class, false ) ) {
			return;
		}

		if ( isset( $classmap[ $class ] ) && is_readable( $classmap[ $class ] ) ) {
			require $classmap[ $class ];
			return;
		}

		$bestPrefix = null;
		$bestLength = -1;
		$bestDirs   = array();
		foreach ( $psr4 as $prefix => $dirs ) {
			$length = strlen( $prefix );
			if ( $length > $bestLength && 0 === strncmp( $class, $prefix, $length ) ) {
				$bestPrefix = $prefix;
				$bestLength = $length;
				$bestDirs   = (array) $dirs;
			}
		}
		if ( null === $bestPrefix ) {
			return;
		}

		$relative = str_replace( '\\', '/', substr( $class, strlen( $bestPrefix ) ) ) . '.php';
		foreach ( $bestDirs as $dir ) {
			$file = rtrim( (string) $dir, '/\\' ) . '/' . $relative;
			if ( is_readable( $file ) ) {
				require $file;
				return;
			}
		}
	}
}

wpml_register_upgrade_autoload_shim();
