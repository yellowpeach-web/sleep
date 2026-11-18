<?php

namespace WPML\TM\ATE\Review;

use WPML\TM\ATE\TranslateEverything\TermBundleMembers;
use WPML\TM\Jobs\TranslationRecord;

class TermBundleReview {

	private $wpdb;

	private $members;

	private $record;

	public function __construct( ?\wpdb $wpdb = null, ?TermBundleMembers $members = null, ?TranslationRecord $record = null ) {
		$this->wpdb    = $wpdb ?: $GLOBALS['wpdb'];
		$this->members = $members ?: new TermBundleMembers( $this->wpdb );
		$this->record  = $record ?: new TranslationRecord( $this->wpdb );
	}

	public function reopen( $recordJobId, $cloneJobId ) {
		$delivered  = [];
		$targetLang = '';
		$rowStates  = [
			TermBundleMembers::STATE_APPLIED  => ICL_TM_COMPLETE,
			TermBundleMembers::STATE_GIVEN_UP => ICL_TM_ATE_UNSOLVABLE,
		];
		foreach ( $this->members->membersOf( (int) $recordJobId ) as $member ) {
			if ( isset( $rowStates[ $member['state'] ] ) ) {
				$delivered[ $member['term_rid'] ] = $member;
				$targetLang                       = $member['target_lang'];
			}
		}
		if ( ! $delivered ) {
			return 0;
		}

		$wpdb = $this->wpdb;
		$found = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT rid, status FROM {$wpdb->prefix}icl_translation_status WHERE rid IN (" . implode( ', ', array_fill( 0, count( $delivered ), '%d' ) ) . ') AND status IN ( %d, %d )',
				array_merge( array_keys( $delivered ), [ ICL_TM_COMPLETE, ICL_TM_ATE_UNSOLVABLE ] )
			)
		);
		$rids = [];
		foreach ( $found as $row ) {
			if ( isset( $delivered[ (int) $row->rid ] ) && (int) $row->status === $rowStates[ $delivered[ (int) $row->rid ]['state'] ] ) {
				$rids[] = (int) $row->rid;
			}
		}
		if ( ! $rids ) {
			return 0;
		}

		foreach ( $rids as $rid ) {
			$this->record->savePreviousStateForRid( $rid );
		}
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}icl_translation_status SET status = %d WHERE rid IN (" . implode( ', ', array_fill( 0, count( $rids ), '%d' ) ) . ')',
				array_merge( [ ICL_TM_IN_PROGRESS ], $rids )
			)
		);

		$rows = [];
		foreach ( $rids as $rid ) {
			$rows[] = [ 'term_taxonomy_id' => $delivered[ $rid ]['term_taxonomy_id'], 'rid' => $rid, 'words' => 0 ];
		}

		return $this->members->add( (int) $cloneJobId, $targetLang, $rows );
	}

	public function complete( $rid ) {
		$reopened = $this->members->reopenedMembersOfRid( (int) $rid );
		if ( ! $reopened ) {
			return 0;
		}

		$wpdb = $this->wpdb;
		$rids = array_column( $reopened, 'term_rid' );
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}icl_translation_status SET status = %d, _prevstate = NULL WHERE rid IN (" . implode( ', ', array_fill( 0, count( $rids ), '%d' ) ) . ') AND status IN ( %d, %d )',
				array_merge( [ ICL_TM_COMPLETE ], $rids, [ ICL_TM_WAITING_FOR_TRANSLATOR, ICL_TM_IN_PROGRESS ] )
			)
		);

		return $this->setStateByBundle( $reopened, TermBundleMembers::STATE_APPLIED );
	}

	public function cancel( $rid ) {
		$reopened = $this->members->reopenedMembersOfRid( (int) $rid );
		foreach ( $reopened as $member ) {
			$this->record->restorePreviousStateForRid( (int) $member['term_rid'] );
		}

		return $this->setStateByBundle( $reopened, TermBundleMembers::STATE_DROPPED );
	}

	private function setStateByBundle( array $members, $state ) {
		$byBundle = [];
		foreach ( $members as $member ) {
			$byBundle[ (int) $member['batch_job_id'] ][] = (int) $member['term_rid'];
		}

		$changed = 0;
		foreach ( $byBundle as $batchJobId => $rids ) {
			$changed += $this->members->setState( $batchJobId, $rids, $state );
		}

		return $changed;
	}
}
