<?php

namespace WPML\TM\ATE\Handshake;

use WPML\TM\ATE\ClonedSites\ReconnectState;
use WPML\TM\ATE\Cancel\PendingCancels;
use WPML\TM\ATE\JobCreation\AteCreateReconciler;
use function WPML\Container\make;

class PendingUnnamed {

	const ACTION_CONFIRM = 'confirm';
	const ACTION_ADOPT   = 'adopt';
	const ACTION_DECLINE = 'decline';
	const ACTION_LEAVE   = 'leave';

	const LEFT_IN_FLIGHT        = 'in_flight';
	const LEFT_MANUAL           = 'manual';
	const LEFT_OWED_CANCEL      = 'owed_cancel';
	const LEFT_CREATE_IN_FLIGHT = 'create_in_flight';
	const LEFT_EPISODE          = 'episode';

	const MAX_ITEMS = 50;

	private $marks;

	private $decline;

	private $owed = null;

	public function __construct( ?Marks $marks = null, ?Decline $decline = null ) {
		$this->marks   = $marks ?: new Marks();
		$this->decline = $decline ?: new Decline( null, $this->marks );
	}

	public static function itemsOf( $answer ) {
		$raw   = is_object( $answer ) && isset( $answer->pending_unnamed ) && is_array( $answer->pending_unnamed ) ? $answer->pending_unnamed : [];
		$items = [];

		foreach ( array_slice( $raw, 0, self::MAX_ITEMS ) as $item ) {
			$item  = is_object( $item ) ? $item : (object) ( is_array( $item ) ? $item : [] );
			$ateId = isset( $item->ate_id ) ? (int) $item->ate_id : 0;

			if ( $ateId < 1 ) {
				continue;
			}

			$items[ $ateId ] = [
				'ate_id'       => $ateId,
				'rid'          => isset( $item->id ) ? (int) $item->id : 0,
				'status'       => isset( $item->status ) ? (int) $item->status : -1,
				'completed_at' => isset( $item->completed_at ) && is_string( $item->completed_at ) ? $item->completed_at : '',
			];
		}

		return $items;
	}

	public function inEpisode() {
		return (bool) ReconnectState::get();
	}

	public function decide( array $item ) {
		$ateId = (int) $item['ate_id'];

		if ( in_array( $ateId, $this->owedCancels(), true ) ) {
			return self::leave( self::LEFT_OWED_CANCEL, 0 );
		}

		$bound = $this->boundJob( $ateId );

		if ( $bound ) {
			return $this->decideBound( $bound );
		}

		$newest = (int) $item['rid'] > 0 ? $this->newestOfRid( (int) $item['rid'] ) : null;

		if ( ! $newest ) {
			return self::decline( Decline::MISSING, 0 );
		}

		$jobId = (int) $newest['job_id'];

		if ( $this->createInFlight( $jobId ) ) {
			return self::leave( self::LEFT_CREATE_IN_FLIGHT, $jobId );
		}

		if ( ! $newest['has_original'] ) {
			return self::decline( Decline::POST_DELETED, 0 );
		}

		if ( (int) $newest['editor_job_id'] > 0 ) {
			return self::decline( Decline::SUPERSEDED, 0 );
		}

		$status = (int) $newest['status'];

		if ( in_array( $status, [ ICL_TM_IN_PROGRESS, ICL_TM_WAITING_FOR_TRANSLATOR, ICL_TM_ATE_NEEDS_RETRY ], true ) ) {
			return [
				'action'    => self::ACTION_ADOPT,
				'reason'    => '',
				'job_id'    => $jobId,
				'automatic' => (bool) $newest['automatic'],
			];
		}

		if ( in_array( $status, [ ICL_TM_NOT_TRANSLATED, ICL_TM_ATE_CANCELLED ], true ) ) {
			return self::decline( Decline::CANCELLED_ON_SITE, 0 );
		}

		return self::decline( Decline::SUPERSEDED, 0 );
	}

	private function decideBound( array $bound ) {
		$jobId = (int) $bound['job_id'];

		if ( $this->createInFlight( $jobId ) ) {
			return self::leave( self::LEFT_CREATE_IN_FLIGHT, $jobId );
		}

		if ( $bound['translated'] ) {
			return [ 'action' => self::ACTION_CONFIRM, 'reason' => '', 'job_id' => $jobId ];
		}

		if ( ! $bound['automatic'] ) {
			return self::leave( self::LEFT_MANUAL, $jobId );
		}

		if ( null === $bound['status'] ) {
			return self::decline( Decline::MISSING, $jobId );
		}

		if ( (int) $bound['newest_job_id'] > $jobId ) {
			return self::decline( Decline::SUPERSEDED, $jobId );
		}

		if ( ! $bound['has_original'] ) {
			return self::decline( Decline::POST_DELETED, $jobId );
		}

		if ( in_array( (int) $bound['status'], [ ICL_TM_ATE_CANCELLED, ICL_TM_NOT_TRANSLATED, ICL_TM_COMPLETE, ICL_TM_DUPLICATE ], true ) ) {
			return self::decline( Decline::CANCELLED_ON_SITE, $jobId );
		}

		return self::leave( self::LEFT_IN_FLIGHT, $jobId );
	}

	public function act( array $item, array $decision ) {
		$ateId = (int) $item['ate_id'];
		$jobId = (int) $decision['job_id'];
		$ok    = true;

		if ( self::ACTION_CONFIRM === $decision['action'] ) {
			$ok = (bool) $this->marks->set( $jobId, $ateId, Marks::UNCONFIRMED );
		} elseif ( self::ACTION_ADOPT === $decision['action'] ) {
			$ok = $this->adopt( $jobId, $ateId, ! empty( $decision['automatic'] ) );
		} elseif ( self::ACTION_DECLINE === $decision['action'] ) {
			$ok = $this->decline->send( $ateId, $jobId, $decision['reason'] );
		}

		Log::write(
			'pending_unnamed',
			[
				'action' => $decision['action'],
				'reason' => $decision['reason'],
				'ok'     => $ok,
				'rid'    => isset( $item['rid'] ) ? (int) $item['rid'] : 0,
			],
			$jobId ?: null,
			$ateId
		);

		return $ok;
	}

	protected function boundJob( $ateId ) {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT j.job_id, j.automatic, j.translated, ts.status,
				        ( SELECT MAX( newer.job_id ) FROM {$wpdb->prefix}icl_translate_job newer WHERE newer.rid = j.rid ) AS newest_job_id,
				        ( SELECT original.translation_id
				            FROM {$wpdb->prefix}icl_translations target
				           INNER JOIN {$wpdb->prefix}icl_translations original
				                   ON original.trid = target.trid
				                  AND original.element_type = target.element_type
				                  AND original.source_language_code IS NULL
				                  AND original.element_id IS NOT NULL
				           WHERE target.translation_id = ts.translation_id
				           LIMIT 1 ) IS NOT NULL AS has_original
				   FROM {$wpdb->prefix}icl_translate_job j
				   LEFT JOIN {$wpdb->prefix}icl_translation_status ts ON ts.rid = j.rid
				  WHERE j.editor_job_id = %d
				  ORDER BY j.job_id DESC
				  LIMIT 1",
				(int) $ateId
			)
		);

		if ( ! is_object( $row ) ) {
			return null;
		}

		return [
			'job_id'        => (int) $row->job_id,
			'automatic'     => (bool) $row->automatic,
			'translated'    => (bool) $row->translated,
			'status'        => null === $row->status ? null : (int) $row->status,
			'newest_job_id' => (int) $row->newest_job_id,
			'has_original'  => (bool) $row->has_original,
		];
	}

	protected function newestOfRid( $rid ) {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT j.job_id, j.editor_job_id, j.automatic, ts.status,
				        ( SELECT original.translation_id
				            FROM {$wpdb->prefix}icl_translations target
				           INNER JOIN {$wpdb->prefix}icl_translations original
				                   ON original.trid = target.trid
				                  AND original.element_type = target.element_type
				                  AND original.source_language_code IS NULL
				                  AND original.element_id IS NOT NULL
				           WHERE target.translation_id = ts.translation_id
				           LIMIT 1 ) IS NOT NULL AS has_original
				   FROM {$wpdb->prefix}icl_translate_job j
				  INNER JOIN {$wpdb->prefix}icl_translation_status ts ON ts.rid = j.rid
				  WHERE j.rid = %d
				  ORDER BY j.job_id DESC
				  LIMIT 1",
				(int) $rid
			)
		);

		if ( ! is_object( $row ) ) {
			return null;
		}

		return [
			'job_id'        => (int) $row->job_id,
			'editor_job_id' => (int) $row->editor_job_id,
			'automatic'     => (bool) $row->automatic,
			'status'        => (int) $row->status,
			'has_original'  => (bool) $row->has_original,
		];
	}

	protected function owedCancels() {
		if ( null === $this->owed ) {
			$this->owed = PendingCancels::pending();
		}

		return $this->owed;
	}

	protected function createInFlight( $jobId ) {
		$expiry = (int) get_option( AteCreateReconciler::IN_FLIGHT_OPTION_PREFIX . (int) $jobId, 0 );

		return $expiry > time();
	}

	protected function adopt( $jobId, $ateId, $automatic ) {
		try {
			make( AteCreateReconciler::class )->bind( [ (int) $jobId => (int) $ateId ], [ (int) $jobId => $automatic ? 'auto' : 'manual' ], null, false );

			return true;
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	private static function decline( $reason, $jobId ) {
		return [ 'action' => self::ACTION_DECLINE, 'reason' => $reason, 'job_id' => (int) $jobId ];
	}

	private static function leave( $why, $jobId ) {
		return [ 'action' => self::ACTION_LEAVE, 'reason' => $why, 'job_id' => (int) $jobId ];
	}
}
