<?php

namespace WPML\TM\ATE\Log;

class HostLog {

	const MAX_MESSAGE = 300;

	private static $writer = null;

	public static function caught( $where, \Throwable $e, $jobId = 0, $ateJobId = 0 ) {
		$line = self::line( $where, $e, $jobId, $ateJobId );

		if ( self::$writer ) {
			call_user_func( self::$writer, $line );

			return;
		}

		if ( ! function_exists( 'error_log' ) ) {
			return;
		}

		try {
			error_log( $line );
		} catch ( \Throwable $ignored ) {
			return;
		}
	}

	public static function line( $where, \Throwable $e, $jobId = 0, $ateJobId = 0 ) {
		$jobs = (int) $jobId > 0 || (int) $ateJobId > 0
			? sprintf( ' job %d (ATE %d):', (int) $jobId, (int) $ateJobId )
			: '';

		return sprintf(
			'WPML translation delivery (%s):%s %s: %s at %s',
			self::oneLine( (string) $where, 40 ),
			$jobs,
			get_class( $e ),
			self::oneLine( (string) $e->getMessage(), self::MAX_MESSAGE ),
			self::origin( $e )
		);
	}

	public static function origin( \Throwable $e ) {
		return $e->getFile() . ':' . $e->getLine();
	}

	private static function oneLine( $text, $max ) {
		$text = trim( (string) preg_replace( '/\s*[\r\n]+\s*/', ' ', $text ) );

		if ( function_exists( 'mb_substr' ) && function_exists( 'mb_strlen' ) ) {
			return mb_strlen( $text ) > $max ? mb_substr( $text, 0, $max ) . '...' : $text;
		}

		return strlen( $text ) > $max ? substr( $text, 0, $max ) . '...' : $text;
	}

	public static function captureForTests( $writer ) {
		self::$writer = $writer;
	}
}
