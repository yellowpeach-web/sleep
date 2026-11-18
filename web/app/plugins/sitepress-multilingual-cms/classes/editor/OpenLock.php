<?php

namespace WPML\TM\Editor;

use WPML\Element\API\TranslationsRepository;
use WPML\FP\Obj;
use WPML\TM\API\Jobs;
use WPML\TM\Jobs\JobLog;
use WPML\Utilities\AdvisoryLock;
use WPML\Utilities\AdvisoryLockFactory;
use function WPML\Container\make;

class OpenLock {

	const TIMEOUT_SECONDS = 20;

	private $trid = 0;

	public function acquire( array $params ) {
		$pair       = $this->resolvePair( $params );
		$this->trid = $pair['trid'];
		$key        = $this->canonicalKey( $pair );

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

	public function readLive() {
		$postTranslations = make( \WPML_Post_Translation::class );

		if ( $postTranslations ) {
			$postTranslations->reload();
		}
		TranslationsRepository::reset();

		if ( $this->trid ) {
			( new \WPML_WP_Cache( 'TranslationManagement::get_translation_job_id' ) )->delete( $this->trid );
		}
	}

	private function resolvePair( array $params ) {
		$trid     = (int) filter_var( Obj::prop( 'trid', $params ), FILTER_SANITIZE_NUMBER_INT );
		$language = (string) filter_var( Obj::prop( 'language_code', $params ), FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$jobId    = (int) filter_var( Obj::prop( 'job_id', $params ), FILTER_SANITIZE_NUMBER_INT );

		if ( ( ! $trid || ! $language ) && $jobId ) {
			$job      = Jobs::get( $jobId ) ?: (object) [];
			$trid     = $trid ?: (int) Obj::prop( 'trid', $job );
			$language = $language ?: (string) Obj::prop( 'language_code', $job );
		}

		return [
			'trid'          => $trid,
			'language_code' => $language,
			'job_id'        => $jobId,
		];
	}

	private function canonicalKey( array $pair ) {
		$trid     = (int) Obj::prop( 'trid', $pair );
		$language = (string) Obj::prop( 'language_code', $pair );
		$jobId    = (int) Obj::prop( 'job_id', $pair );

		if ( $trid && $language ) {
			return 'editor_open_' . $trid . '_' . $language;
		}

		return $jobId ? 'editor_open_job_' . $jobId : '';
	}
}
