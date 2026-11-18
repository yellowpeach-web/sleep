<?php

namespace WPML\TM\ATE\Handshake\Corrections;

use WPML\TM\ATE\Handshake\Decline;
use WPML\TM\ATE\Handshake\Log;
use WPML\TM\ATE\Handshake\Marks;
use WPML\TM\ATE\Handshake\Runner;
use WPML\TM\ATE\Handshake\State;

class Decision {

	private $client;

	private $putBackInProgress;

	private $startCollection;

	private $decline;

	private $forgetEarlierWrites;

	public function __construct( ?Client $client = null, $putBackInProgress = null, $startCollection = null, $decline = null, $forgetEarlierWrites = null ) {
		$this->client            = $client ?: new Client();
		$this->putBackInProgress = $putBackInProgress ?: function ( array $ateJobIds ) {
			return \WPML\Container\make( \WPML\TM\ATE\Retranslation\RetranslationPreparer::class )->putBackInProgress( $ateJobIds );
		};
		$this->startCollection   = $startCollection ?: function () {
			State::recordInFlightSummary( ( new Marks() )->withUnconfirmed( ( new \WPML\TM\ATE\PullDelivery\PendingJobs() )->summary() ) );
			Runner::spawn( Runner::TRIGGER_MANUAL );
		};
		$this->decline           = $decline ?: function ( $ateJobId, $jobId, $reason ) {
			return ( new Decline() )->send( (int) $ateJobId, (int) $jobId, (string) $reason );
		};
		$this->forgetEarlierWrites = $forgetEarlierWrites ?: function ( array $jobIds ) {
			self::forgetEarlierWrites( new Marks(), $jobIds );
		};
	}

	public static function forgetEarlierWrites( Marks $marks, array $jobIds ) {
		if ( ! $jobIds ) {
			return;
		}

		foreach ( $marks->marksOf( $jobIds ) as $jobId => $mark ) {
			if ( Marks::UNCONFIRMED === $mark['mark'] ) {
				$marks->clear( (int) $jobId );
				Log::write( 'unconfirmed_cleared', [ 'correction_accepted' => true ], (int) $jobId, (int) $mark['ate_job_id'] );
			}
		}
	}

	public function apply( array $ticked, array $offered ) {
		$byId   = self::selectable( $offered );
		$accept = array_values( array_intersect( array_keys( $byId ), array_map( 'intval', $ticked ) ) );

		if ( ! $accept ) {
			return [
				'ok'       => true,
				'accepted' => 0,
				'error'    => '',
			];
		}

		$answer = $this->client->decide( $accept, [] );

		if ( ! $answer['ok'] ) {
			Log::write( 'corrections_decided', [ 'op' => 'apply', 'ok' => false, 'count' => count( $accept ), 'error' => $answer['error'] ] );

			return [
				'ok'       => false,
				'accepted' => 0,
				'error'    => $answer['error'],
			];
		}

		$put      = (array) call_user_func( $this->putBackInProgress, $accept );
		$fresh    = isset( $put['fresh'] ) ? (array) $put['fresh'] : [];
		$outdated = isset( $put['outdated'] ) ? (array) $put['outdated'] : [];
		$declined = 0;

		call_user_func( $this->forgetEarlierWrites, array_map( 'intval', $fresh ) );

		foreach ( $outdated as $job ) {
			$ateJobId = (int) $job['editor_job_id'];
			$reason   = isset( $byId[ $ateJobId ] ) && ! empty( $byId[ $ateJobId ]['edited'] ) ? Decline::EDITED_BY_CLIENT : Decline::SUPERSEDED;

			if ( call_user_func( $this->decline, $ateJobId, (int) $job['job_id'], $reason ) ) {
				$declined++;
			}
		}

		Log::write(
			'corrections_decided',
			[
				'op'       => 'apply',
				'ok'       => true,
				'count'    => count( $accept ),
				'fresh'    => count( $fresh ),
				'declined' => $declined,
			]
		);

		if ( $fresh ) {
			call_user_func( $this->startCollection );
		}

		return [
			'ok'       => true,
			'accepted' => count( $accept ),
			'error'    => '',
		];
	}

	public function skipAll( array $offered ) {
		$all = array_values( array_unique( array_map( 'intval', array_column( $offered, 'ate_id' ) ) ) );

		if ( ! $all ) {
			return [
				'ok'      => true,
				'skipped' => 0,
				'error'   => '',
			];
		}

		$answer = $this->client->decide( [], $all );

		Log::write( 'corrections_decided', [ 'op' => 'skip', 'ok' => $answer['ok'], 'count' => count( $all ), 'error' => $answer['error'] ] );

		return [
			'ok'      => $answer['ok'],
			'skipped' => $answer['ok'] ? count( $all ) : 0,
			'error'   => $answer['error'],
		];
	}

	private static function selectable( array $offered ) {
		$out = [];

		foreach ( $offered as $row ) {
			if ( empty( $row['deleted'] ) && (int) $row['ate_id'] > 0 ) {
				$out[ (int) $row['ate_id'] ] = $row;
			}
		}

		return $out;
	}
}
