<?php

namespace WPML\LanguageEditor\Save;

class SaveTask {

	const TASK_TYPE = 'structural_language_save';

	const STATUS_PENDING              = 0;
	const STATUS_IN_PROGRESS          = 1;
	const STATUS_PAUSED               = 2;
	const STATUS_COMPLETED            = 3;
	const STATUS_COMPLETED_WITH_SKIPS = 4;
	const STATUS_FAILED               = 5;

	private static $statusNames = [
		self::STATUS_PENDING              => 'pending',
		self::STATUS_IN_PROGRESS          => 'in-progress',
		self::STATUS_PAUSED               => 'paused',
		self::STATUS_COMPLETED            => 'completed',
		self::STATUS_COMPLETED_WITH_SKIPS => 'completed-with-skips',
		self::STATUS_FAILED               => 'failed',
	];

	private $taskId;

	private $status = self::STATUS_PENDING;

	private $totalCount = 0;

	private $completedCount = 0;

	private $payload = [];

	private $retryCount = 0;

	private $startingDate;

	private $operationRecord = null;

	public static function statusName( $status ) {
		return isset( self::$statusNames[ (int) $status ] ) ? self::$statusNames[ (int) $status ] : 'unknown';
	}

	public static function isTerminalStatus( $status ) {
		return in_array( (int) $status, [ self::STATUS_COMPLETED, self::STATUS_COMPLETED_WITH_SKIPS, self::STATUS_FAILED ], true );
	}

	public function setTaskId( $id ) {
		$this->taskId = is_numeric( $id ) ? (int) $id : null;
	}

	public function getTaskId() {
		return $this->taskId;
	}

	public function setStatus( $status ) {
		$this->status = (int) $status;
	}

	public function getStatus() {
		return $this->status;
	}

	public function isTerminal() {
		return self::isTerminalStatus( $this->status );
	}

	public function setTotalCount( $count ) {
		$this->totalCount = (int) $count;
	}

	public function getTotalCount() {
		return $this->totalCount;
	}

	public function setCompletedCount( $count ) {
		$this->completedCount = (int) $count;
	}

	public function getCompletedCount() {
		return $this->completedCount;
	}

	public function addCompletedCount( $delta ) {
		$this->completedCount += (int) $delta;
	}

	public function setPayload( $payload ) {
		$this->payload = is_array( $payload ) ? $payload : [];
	}

	public function getPayload() {
		return $this->payload;
	}

	public function getCursor( $key, $default = null ) {
		return array_key_exists( $key, $this->payload ) ? $this->payload[ $key ] : $default;
	}

	public function setCursor( $key, $value ) {
		$this->payload[ $key ] = $value;
	}

	public function setRetryCount( $count ) {
		$this->retryCount = (int) $count;
	}

	public function getRetryCount() {
		return $this->retryCount;
	}

	public function setOperationRecord( $record ) {
		$this->operationRecord = is_array( $record ) ? $record : null;
	}

	public function getOperationRecord() {
		return $this->operationRecord;
	}

	public function setStartingDate( $date ) {
		$this->startingDate = $date;
	}

	public function getStartingDate() {
		return $this->startingDate;
	}

	public function getPercent() {
		if ( $this->totalCount <= 0 ) {
			return $this->isTerminal() ? 100 : 0;
		}
		return (int) min( 100, floor( $this->completedCount * 100 / $this->totalCount ) );
	}

	public function toResponse() {
		$payload = $this->payload;
		$units   = isset( $payload['units'] ) && is_array( $payload['units'] ) ? $payload['units'] : [];
		$unitIdx = isset( $payload['unitIdx'] ) ? (int) $payload['unitIdx'] : 0;
		$unit    = isset( $units[ $unitIdx ] ) && is_array( $units[ $unitIdx ] ) ? $units[ $unitIdx ] : null;

		$changeIdx   = ( $unit && isset( $unit['changeIdx'] ) ) ? (int) $unit['changeIdx'] : null;
		$changeCode  = null;
		$changeLabel = null;
		if ( null !== $changeIdx && isset( $payload['changes'][ $changeIdx ]['code'] ) ) {
			$changeCode  = (string) $payload['changes'][ $changeIdx ]['code'];
			$changeLabel = self::changeLabel( (array) $payload['changes'][ $changeIdx ] );
		}

		$remap = self::remapOutcomes( $payload );

		$response = [
			'taskId'      => $this->taskId,
			'status'      => self::statusName( $this->status ),
			'stepIndex'   => $unitIdx,
			'stepCount'   => count( $units ),
			'phase'       => $unit && isset( $unit['phase'] ) ? $unit['phase'] : null,
			'changeIndex' => $changeIdx,
			'changeCode'  => $changeCode,
			'changeLabel' => $changeLabel,
			'total'       => $this->totalCount,
			'completed'   => $this->completedCount,
			'percent'     => $this->getPercent(),
			'skipped'     => self::skippedWithLabels( $payload ),
			'isComplete'  => $this->isTerminal(),
			'operation_record' => $this->operationRecord,
		];

		if ( $remap ) {
			$response['remap'] = $remap;
		}

		return $response;
	}

	private static function skippedWithLabels( array $payload ) {
		$skipped = isset( $payload['skipped'] ) && is_array( $payload['skipped'] ) ? $payload['skipped'] : [];
		$changes = isset( $payload['changes'] ) && is_array( $payload['changes'] ) ? $payload['changes'] : [];
		$out     = [];
		foreach ( $skipped as $entry ) {
			$entry   = (array) $entry;
			$unitKey = isset( $entry['phase'] ) ? (string) $entry['phase'] : '';
			$colon   = strrpos( $unitKey, ':' );
			if ( false !== $colon ) {
				$changeIdx = (int) substr( $unitKey, $colon + 1 );
				if ( isset( $changes[ $changeIdx ] ) && is_array( $changes[ $changeIdx ] ) && isset( $changes[ $changeIdx ]['code'] ) ) {
					$entry['code']  = (string) $changes[ $changeIdx ]['code'];
					$entry['label'] = self::changeLabel( $changes[ $changeIdx ] );
				}
			}
			$out[] = $entry;
		}

		return $out;
	}

	private static function remapOutcomes( array $payload ) {
		$outcomes = isset( $payload['phaseOutcomes'] ) && is_array( $payload['phaseOutcomes'] )
			? $payload['phaseOutcomes']
			: [];
		$prefix   = \WPML\LanguageEditor\Save\Phase\CountryPhase::ID . ':';
		$out      = [];

		foreach ( $outcomes as $unitKey => $notes ) {
			if ( ! is_array( $notes ) || 0 !== strpos( (string) $unitKey, $prefix ) ) {
				continue;
			}

			$outcome = isset( $notes['remap'] ) ? (string) $notes['remap'] : '';
			if ( '' === $outcome || 'not_requested' === $outcome ) {
				continue;
			}

			$changeIdx = (int) substr( (string) $unitKey, strlen( $prefix ) );
			if ( ! isset( $payload['changes'][ $changeIdx ]['code'] ) ) {
				continue;
			}

			$change = (array) $payload['changes'][ $changeIdx ];
			$entry  = [
				'code'    => (string) $change['code'],
				'label'   => self::changeLabel( $change ),
				'outcome' => $outcome,
			];

			foreach ( [ 'reason', 'target', 'mode', 'detail' ] as $key ) {
				if ( isset( $notes[ $key ] ) && '' !== $notes[ $key ] ) {
					$entry[ $key ] = (string) $notes[ $key ];
				}
			}
			if ( isset( $notes['kept'] ) ) {
				$entry['kept'] = (bool) $notes['kept'];
			}

			$out[] = $entry;
		}

		return $out;
	}

	private static function changeLabel( array $change ) {
		$code = isset( $change['code'] ) ? (string) $change['code'] : '';
		if ( '' === $code ) {
			return null;
		}

		$name = isset( $change['name'] ) ? trim( (string) $change['name'] ) : '';
		if ( '' !== $name && strcasecmp( $name, $code ) !== 0 ) {
			return $name;
		}

		if ( class_exists( \WPML\LanguageEditor\RemovedLanguages\DisplayNames::class ) ) {
			$names  = \WPML\LanguageEditor\RemovedLanguages\DisplayNames::forCodes( [ $code ] );
			$stored = isset( $names[ $code ] ) ? trim( (string) $names[ $code ] ) : '';
			if ( '' !== $stored && strcasecmp( $stored, $code ) !== 0 ) {
				return $stored;
			}
		}

		$displayCode = self::displayCodeOfChange( $change, $code );

		return '' !== $displayCode ? $displayCode : null;
	}

	private static function displayCodeOfChange( array $change, $code ) {
		foreach ( [ 'new', 'old' ] as $side ) {
			if ( isset( $change['display_code'][ $side ] ) ) {
				$value = trim( (string) $change['display_code'][ $side ] );
				if ( '' !== $value ) {
					return $value;
				}
			}
		}

		if ( ! class_exists( \WPML\Language\ActiveLanguagesReadModel::class ) ) {
			return '';
		}

		$rows = \WPML\Language\ActiveLanguagesReadModel::rows();
		if ( ! isset( $rows[ $code ] ) ) {
			return '';
		}

		$row = (array) $rows[ $code ];

		return isset( $row['display_code'] ) ? trim( (string) $row['display_code'] ) : '';
	}

	public function serialize() {
		return [
			'task_type'       => self::TASK_TYPE,
			'task_status'     => $this->status,
			'starting_date'   => $this->startingDate,
			'total_count'     => $this->totalCount,
			'completed_count' => $this->completedCount,
			'payload'         => serialize( $this->payload ),
			'retry_count'     => $this->retryCount,
		];
	}
}
