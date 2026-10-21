<?php

namespace WPML\TM\Editor;

use WPML\FP\Obj;
use WPML\TM\API\Jobs;
use WPML\TM\Jobs\JobLog;
use WPML\Utilities\AdvisoryLock;
use WPML\Utilities\AdvisoryLockFactory;
use function WPML\Container\make;

class OpenLock {

	const TIMEOUT_SECONDS = 20;

	public function acquire( array $params ) {
		$key = $this->canonicalKey( $params );

		if ( ! $key ) {
			return null;
		}

		$lockFactory = make( AdvisoryLockFactory::class );

		if ( ! $lockFactory ) {
			return null;
		}

		$lock = $lockFactory->create( $key );

		if ( $lock->acquire( self::TIMEOUT_SECONDS ) ) {
			return $lock;
		}

		JobLog::add( 'editor_open_lock_degraded', [ 'key' => $key ] );

		return null;
	}

	private function canonicalKey( array $params ) {
		$trid     = (int) filter_var( Obj::prop( 'trid', $params ), FILTER_SANITIZE_NUMBER_INT );
		$language = (string) filter_var( Obj::prop( 'language_code', $params ), FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$jobId    = (int) filter_var( Obj::prop( 'job_id', $params ), FILTER_SANITIZE_NUMBER_INT );

		if ( ( ! $trid || ! $language ) && $jobId ) {
			$job      = Jobs::get( $jobId ) ?: (object) [];
			$trid     = $trid ?: (int) Obj::prop( 'trid', $job );
			$language = $language ?: (string) Obj::prop( 'language_code', $job );
		}

		if ( $trid && $language ) {
			return 'editor_open_' . $trid . '_' . $language;
		}

		return $jobId ? 'editor_open_job_' . $jobId : '';
	}
}
