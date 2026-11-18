<?php

namespace WPML\EditHistory;

class SendRows {

	const MAX_JOBS = 10;

	public static function add( array $rows, SendRow $row, $isDecided = null ): array {
		$byJob = [];
		foreach ( $rows as $kept ) {
			$jobId = (int) ( $kept['job_id'] ?? 0 );
			if ( $jobId === $row->jobId || ! $isDecided || ! $isDecided( $jobId ) ) {
				$byJob[ $jobId ][] = $kept;
			}
		}

		unset( $byJob[ $row->jobId ] );
		$byJob[ $row->jobId ] = array_merge( self::lowestOf( $rows, $row->jobId ), [ $row->toArray() ] );

		$byJob = array_slice( $byJob, -self::MAX_JOBS, null, true );

		return array_merge( ...array_values( $byJob ) );
	}

	public static function bind( array $rows, int $jobId, int $ateJobId ): array {
		return array_map(
			fn( array $row ) => (int) ( $row['job_id'] ?? 0 ) === $jobId && empty( $row['ate_job_id'] )
				? array_merge( $row, [ 'ate_job_id' => $ateJobId ] )
				: $row,
			$rows
		);
	}

	private static function lowestOf( array $rows, int $jobId ): array {
		$lowest = null;
		foreach ( $rows as $row ) {
			if ( (int) ( $row['job_id'] ?? 0 ) === $jobId && ( null === $lowest || (int) $row['watermark'] < (int) $lowest['watermark'] ) ) {
				$lowest = $row;
			}
		}

		return null === $lowest ? [] : [ $lowest ];
	}
}
