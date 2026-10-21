<?php

namespace WPML\StringTranslation\Infrastructure\Core\Command;

use WP_Filesystem_Base;
use WP_Filesystem_Direct;

class SaveFileCommand {

	protected $filesystem;

	public function __construct(
		$filesystem
	) {
		$this->filesystem = $filesystem;
	}

	public function run( string $filepath, string $data ) {
		$tmpFilepath = $this->getTmpFilepath( $filepath );

		$chmod = defined( 'FS_CHMOD_FILE' ) ? FS_CHMOD_FILE : 0644;

		if ( ! $this->filesystem->put_contents( $tmpFilepath, $data, $chmod ) ) {
			$this->filesystem->delete( $tmpFilepath );
			return false;
		}

		if ( $this->publish( $tmpFilepath, $filepath ) ) {
			self::invalidatePhpFile( $filepath );
			return true;
		}

		$this->filesystem->delete( $tmpFilepath );

		return false;
	}

	public static function invalidatePhpFile( string $filepath ) {
		if ( '.php' !== substr( $filepath, -4 ) ) {
			return;
		}

		if ( function_exists( 'wp_opcache_invalidate' ) ) {
			wp_opcache_invalidate( $filepath, true );
			return;
		}

		$restrict = (string) ini_get( 'opcache.restrict_api' );
		$included = get_included_files();
		$script   = isset( $included[0] ) ? (string) realpath( $included[0] ) : '';
		if ( function_exists( 'opcache_invalidate' ) && ( '' === $restrict || 0 === stripos( $script, $restrict ) ) ) {
			@opcache_invalidate( $filepath, true );
		}
	}

	private function publish( string $tmpFilepath, string $filepath ): bool {
		if ( $this->filesystem instanceof WP_Filesystem_Direct ) {
			return (bool) @rename( $tmpFilepath, $filepath );
		}

		return (bool) $this->filesystem->move( $tmpFilepath, $filepath, true );
	}

	private function getTmpFilepath( string $filepath ) : string {
		$dir      = dirname( $filepath );
		$basename = basename( $filepath );

		for ( $i = 0; $i < 5; $i++ ) {
			$tmpFilepath = $dir . '/' . $basename . '.' . $this->uniqueSuffix() . '.tmp';
			if ( ! file_exists( $tmpFilepath ) ) {
				return $tmpFilepath;
			}
		}

		return $dir . '/' . $basename . '.' . md5( microtime( true ) . $filepath . $this->uniqueSuffix() ) . '.tmp';
	}

	private function uniqueSuffix() : string {
		try {
			$random = bin2hex( random_bytes( 4 ) );
		} catch ( \Exception $e ) {
			$random = dechex( crc32( uniqid( '', true ) ) );
		}

		return str_replace( '.', '', uniqid( '', true ) ) . $random;
	}
}
