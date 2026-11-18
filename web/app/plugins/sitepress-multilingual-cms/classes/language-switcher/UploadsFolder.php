<?php

namespace WPML\LanguageSwitcher;

class UploadsFolder {

	public static function contains( $path, $base = null ) {
		if ( null === $base ) {
			$uploads = wp_upload_dir( null, false );
			$base    = is_array( $uploads ) && isset( $uploads['basedir'] ) ? $uploads['basedir'] : '';
		}

		return FolderIdentity::inside( $path, array( (string) $base ) );
	}

	public static function useFileSystem( $realpath = null, $stat = null ) {
		FolderIdentity::useFileSystem( $realpath, $stat );
	}
}
