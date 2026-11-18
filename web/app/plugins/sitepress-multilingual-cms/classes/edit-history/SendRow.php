<?php

namespace WPML\EditHistory;

class SendRow {

	public $jobId;

	public $ateJobId;

	public $watermark;

	public $unitsHash;

	public $lastApplied;

	public $builtAt;

	public $droppedJob;

	public $history;

	public function __construct( int $jobId, $ateJobId, int $watermark, string $unitsHash, $lastApplied, int $builtAt, $droppedJob = null, bool $history = false ) {
		$this->jobId       = $jobId;
		$this->ateJobId    = $ateJobId;
		$this->watermark   = $watermark;
		$this->unitsHash   = $unitsHash;
		$this->lastApplied = $lastApplied;
		$this->builtAt     = $builtAt;
		$this->droppedJob  = $droppedJob;
		$this->history     = $history;
	}

	public function toArray(): array {
		$row = [
			'job_id'       => $this->jobId,
			'ate_job_id'   => $this->ateJobId,
			'watermark'    => $this->watermark,
			'units_hash'   => $this->unitsHash,
			'last_applied' => $this->lastApplied,
			'built_at'     => $this->builtAt,
		];

		if ( $this->droppedJob ) {
			$row['dropped_job'] = $this->droppedJob;
		}

		return $this->history ? $row + [ 'history' => true ] : $row;
	}

	public static function fromArray( array $row ): self {
		$lastApplied = $row['last_applied'] ?? 'unknown';

		return new self(
			(int) ( $row['job_id'] ?? 0 ),
			isset( $row['ate_job_id'] ) ? (int) $row['ate_job_id'] : null,
			(int) ( $row['watermark'] ?? 0 ),
			(string) ( $row['units_hash'] ?? '' ),
			is_numeric( $lastApplied ) ? (int) $lastApplied : (string) $lastApplied,
			(int) ( $row['built_at'] ?? 0 ),
			is_array( $row['dropped_job'] ?? null ) ? $row['dropped_job'] : null,
			! empty( $row['history'] )
		);
	}
}
