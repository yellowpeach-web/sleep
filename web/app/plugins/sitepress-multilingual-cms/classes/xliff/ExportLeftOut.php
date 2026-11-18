<?php

namespace WPML\TM\XLIFF;

use WPML\TM\Jobs\JobLog;
use WPML\Translation\TranslationElements\CompressionTracker;

class ExportLeftOut {

	const REASON_ZLIB_MISSING = 'zlib_missing';

	const HEADER = 'X-WPML-XLIFF-Left-Out';

	const HEADER_MAX_IDS = 100;

	const LOG_ID = 'xliff_export_jobs_left_out';

	private $jobIdsByReason = [];

	private $holdsUnreadableFields;

	public function __construct( ?callable $holdsUnreadableFields = null ) {
		$this->holdsUnreadableFields = $holdsUnreadableFields ?: [ CompressionTracker::class, 'shouldShowMissingZlibError' ];
	}

	public function reasonFor( $jobId ) {
		if ( call_user_func( $this->holdsUnreadableFields, (int) $jobId ) ) {
			return self::REASON_ZLIB_MISSING;
		}

		return null;
	}

	public function leaveOut( $jobId, $reason ) {
		$this->jobIdsByReason[ (string) $reason ][] = (int) $jobId;
	}

	public function isEmpty() {
		return ! $this->jobIdsByReason;
	}

	public function jobIdsByReason() {
		return $this->jobIdsByReason;
	}

	public function headerValue() {
		$value = [];
		foreach ( $this->jobIdsByReason as $reason => $jobIds ) {
			$value[ $reason ] = [
				'count' => count( $jobIds ),
				'ids'   => array_slice( $jobIds, 0, self::HEADER_MAX_IDS ),
			];
		}

		return (string) wp_json_encode( $value );
	}

	public function writeToJobLog() {
		if ( $this->isEmpty() ) {
			return;
		}

		JobLog::runInGroup(
			JobLog::GROUP_ID_XLIFF_EXPORT,
			'XLIFF export',
			function () {
				foreach ( $this->jobIdsByReason as $reason => $jobIds ) {
					JobLog::addError(
						self::LOG_ID,
						[
							'summary' => self::summary( $reason, count( $jobIds ) ),
							'reason'  => $reason,
							'count'   => count( $jobIds ),
							'jobIds'  => $jobIds,
						]
					);
				}
			}
		);
	}

	private static function summary( $reason, $count ) {
		$jobs = 1 === $count ? '1 job' : $count . ' jobs';
		if ( self::REASON_ZLIB_MISSING === $reason ) {
			return $jobs . ' not exported: zlib is missing on this host';
		}

		return $jobs . ' not exported: ' . $reason;
	}
}
