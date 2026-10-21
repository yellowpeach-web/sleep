<?php

namespace WPML\LanguageSwitcher;

class UploadsFolder {

	const MAX_DEPTH = 64;

	private static $realpath;

	private static $stat;

	public static function useFileSystem( $realpath = null, $stat = null ) {
		self::$realpath = $realpath;
		self::$stat     = $stat;
	}

	public static function contains( $path, $base = null ) {
		if ( null === $base ) {
			$uploads = wp_upload_dir( null, false );
			$base    = is_array( $uploads ) && isset( $uploads['basedir'] ) ? $uploads['basedir'] : '';
		}

		$base = (string) $base;
		$path = (string) $path;

		if ( '' === $base || '' === $path ) {
			return false;
		}

		$paths = self::variants( $path );
		$roots = self::variants( $base );

		foreach ( $paths as $candidate ) {
			foreach ( $roots as $root ) {
				if ( self::isInside( $candidate, $root ) ) {
					return true;
				}
			}
		}

		$identity = self::insideByIdentity( $paths, $roots );

		if ( null !== $identity ) {
			return $identity;
		}

		foreach ( $paths as $candidate ) {
			foreach ( $roots as $root ) {
				if ( self::isInside( self::lower( $candidate ), self::lower( $root ) ) ) {
					return true;
				}
			}
		}

		return false;
	}

	private static function insideByIdentity( array $paths, array $roots ) {
		$uploads = array();

		foreach ( $roots as $root ) {
			$stat = self::stat( $root );

			if ( $stat && ! empty( $stat['ino'] ) ) {
				$uploads[ $stat['dev'] . ':' . $stat['ino'] ] = true;
			}
		}

		if ( ! $uploads ) {
			return null;
		}

		$usable = true;

		foreach ( $paths as $candidate ) {
			$current = $candidate;

			for ( $level = 0; $level < self::MAX_DEPTH; $level++ ) {
				$stat = self::stat( $current );

				if ( $stat ) {
					if ( empty( $stat['ino'] ) ) {
						$usable = false;
					} elseif ( isset( $uploads[ $stat['dev'] . ':' . $stat['ino'] ] ) ) {
						return true;
					}
				}

				$parent = dirname( $current );

				if ( $parent === $current || '.' === $parent ) {
					break;
				}

				$current = $parent;
			}
		}

		return $usable ? false : null;
	}

	private static function variants( $path ) {
		$normalized = self::normalize( $path );
		$rest       = array();
		$existing   = $normalized;

		while ( '' !== $existing && '/' !== $existing && false === self::realpath( $existing ) ) {
			array_unshift( $rest, basename( $existing ) );
			$existing = dirname( $existing );
		}

		$real = '' === $existing ? false : self::realpath( $existing );

		if ( false === $real ) {
			return array( $normalized );
		}

		$resolved = self::normalize( $real . ( $rest ? '/' . implode( '/', $rest ) : '' ) );

		return array_values( array_unique( array( $normalized, $resolved ) ) );
	}

	private static function isInside( $candidate, $root ) {
		return $candidate === $root || 0 === strpos( $candidate . '/', $root . '/' );
	}

	private static function normalize( $path ) {
		$path     = str_replace( '\\', '/', $path );
		$segments = array();

		foreach ( explode( '/', $path ) as $segment ) {
			if ( '' === $segment || '.' === $segment ) {
				continue;
			}

			if ( '..' === $segment ) {
				array_pop( $segments );
				continue;
			}

			$segments[] = $segment;
		}

		return ( 0 === strpos( $path, '/' ) ? '/' : '' ) . implode( '/', $segments );
	}

	private static function lower( $text ) {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
	}

	private static function realpath( $path ) {
		return self::$realpath ? call_user_func( self::$realpath, $path ) : realpath( $path );
	}

	private static function stat( $path ) {
		$stat = self::$stat ? call_user_func( self::$stat, $path ) : @stat( $path );

		return is_array( $stat ) && isset( $stat['dev'], $stat['ino'] ) ? $stat : false;
	}
}
