<?php

namespace WPML\Upgrade\Commands;

require_once __DIR__ . '/../../../inc/constants-since-5-0.php';

use WPML\TM\ATE\TranslateEverything\TermBundleMembers;
use WPML\TM\Jobs\JobLog;

class ClearNameTakenTermRefusalsForCleanedSlugs extends ClearNameTakenTermRefusals {

	const CLEAN_SLUG_CHARACTERS = '[^-a-z0-9_%]';

	const PAGE_SIZE = 500;

	private $db;

	private $reopened = 0;

	public function __construct( array $args ) {
		parent::__construct( $args );
		$this->db = $args[0];
	}

	public function run_admin() {
		$this->reopened = 0;

		if ( ! $this->reopenTermJobs() ) {
			return false;
		}

		if ( $this->hasBundleMembersTable() && ( ! $this->reopenBundleMembers() || ! $this->closeBundlesWithoutGivenUpMembers() ) ) {
			return false;
		}

		return parent::run_admin();
	}

	public function getReopened() {
		return $this->reopened;
	}

	protected function pageSize() {
		return self::PAGE_SIZE;
	}

	private function reopenTermJobs() {
		$wpdb = $this->db;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT job.job_id AS job_id, job.rid AS rid
				   FROM {$wpdb->prefix}icl_translate_unsolvable_jobs error
				  INNER JOIN {$wpdb->prefix}icl_translate_job job ON job.job_id = error.job_id
				  INNER JOIN {$wpdb->prefix}icl_translation_status status ON status.rid = job.rid
				  INNER JOIN {$wpdb->prefix}icl_translations translation ON translation.translation_id = status.translation_id
				  INNER JOIN {$wpdb->prefix}icl_translations source_row
				    ON source_row.trid = translation.trid
				   AND source_row.element_type = translation.element_type
				   AND source_row.source_language_code IS NULL
				  INNER JOIN {$wpdb->term_taxonomy} source_tt ON source_tt.term_taxonomy_id = source_row.element_id
				  INNER JOIN {$wpdb->terms} source_term ON source_term.term_id = source_tt.term_id
				  WHERE error.error_message LIKE %s
				    AND ( source_term.slug REGEXP %s OR CAST( source_term.slug AS BINARY ) <> CAST( LOWER( source_term.slug ) AS BINARY ) )
				    AND status.status = %d
				    AND translation.element_type LIKE %s
				    AND NOT EXISTS ( SELECT 1 FROM {$wpdb->prefix}icl_translate_job newer WHERE newer.rid = job.rid AND newer.job_id > job.job_id )
				  ORDER BY job.job_id
				  LIMIT %d",
				'%' . $wpdb->esc_like( self::REASON_START ) . '%',
				self::CLEAN_SLUG_CHARACTERS,
				ICL_TM_ATE_UNSOLVABLE,
				$wpdb->esc_like( 'tax_' ) . '%',
				$this->pageSize()
			)
		);

		if ( ! is_array( $rows ) || '' !== (string) $wpdb->last_error ) {
			return false;
		}

		if ( ! $rows ) {
			return true;
		}

		$rids = array_map( 'intval', array_column( $rows, 'rid' ) );
		if ( false === $this->setStatus( $rids, ICL_TM_ATE_CANCELLED ) ) {
			return false;
		}

		$this->log( 'name_taken_term_jobs_reopened', [ 'job_ids' => array_map( 'intval', array_column( $rows, 'job_id' ) ) ] );

		return count( $rows ) < $this->pageSize();
	}

	private function reopenBundleMembers() {
		$wpdb = $this->db;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT member.batch_job_id AS batch_job_id, member.term_rid AS term_rid
				   FROM {$wpdb->prefix}icl_term_batch_members member
				  INNER JOIN {$wpdb->prefix}icl_translation_status status ON status.rid = member.term_rid
				  INNER JOIN {$wpdb->prefix}icl_translations translation ON translation.translation_id = status.translation_id
				  INNER JOIN {$wpdb->prefix}icl_translations source_row
				    ON source_row.trid = translation.trid
				   AND source_row.element_type = translation.element_type
				   AND source_row.source_language_code IS NULL
				  INNER JOIN {$wpdb->term_taxonomy} source_tt ON source_tt.term_taxonomy_id = source_row.element_id
				  INNER JOIN {$wpdb->terms} source_term ON source_term.term_id = source_tt.term_id
				  WHERE member.state = %s
				    AND member.reason LIKE %s
				    AND ( source_term.slug REGEXP %s OR CAST( source_term.slug AS BINARY ) <> CAST( LOWER( source_term.slug ) AS BINARY ) )
				  ORDER BY member.batch_job_id, member.term_rid
				  LIMIT %d",
				TermBundleMembers::STATE_GIVEN_UP,
				'%' . $wpdb->esc_like( self::REASON_START ) . '%',
				self::CLEAN_SLUG_CHARACTERS,
				$this->pageSize()
			)
		);

		if ( ! is_array( $rows ) || '' !== (string) $wpdb->last_error ) {
			return false;
		}

		if ( ! $rows ) {
			return true;
		}

		$ridsByBundle = [];
		foreach ( $rows as $row ) {
			$ridsByBundle[ (int) $row->batch_job_id ][] = (int) $row->term_rid;
		}

		foreach ( $ridsByBundle as $bundleJobId => $rids ) {
			if ( false === $this->setStatus( $rids, ICL_TM_ATE_CANCELLED ) ) {
				return false;
			}

			$dropped = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->prefix}icl_term_batch_members SET state = %s
					  WHERE batch_job_id = %d AND state = %s AND term_rid IN ( " . implode( ',', array_fill( 0, count( $rids ), '%d' ) ) . ' )',
					array_merge( [ TermBundleMembers::STATE_DROPPED, $bundleJobId, TermBundleMembers::STATE_GIVEN_UP ], $rids )
				)
			);
			if ( false === $dropped ) {
				return false;
			}
		}

		$this->log( 'name_taken_term_bundle_members_reopened', [ 'term_rids_by_bundle_job_id' => $ridsByBundle ] );

		return count( $rows ) < $this->pageSize();
	}

	private function closeBundlesWithoutGivenUpMembers() {
		$wpdb = $this->db;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT job.job_id AS job_id, job.rid AS rid, SUM( member.state = %s ) AS applied
				   FROM {$wpdb->prefix}icl_term_batch_members member
				  INNER JOIN {$wpdb->prefix}icl_translate_job job ON job.job_id = member.batch_job_id
				  INNER JOIN {$wpdb->prefix}icl_translation_status status ON status.rid = job.rid
				  WHERE status.status = %d
				    AND NOT EXISTS ( SELECT 1 FROM {$wpdb->prefix}icl_translate_job newer WHERE newer.rid = job.rid AND newer.job_id > job.job_id )
				  GROUP BY job.job_id, job.rid
				 HAVING SUM( member.state IN ( %s, %s ) ) = 0
				    AND SUM( member.state = %s AND member.reason LIKE %s ) > 0
				  ORDER BY job.job_id
				  LIMIT %d",
				TermBundleMembers::STATE_APPLIED,
				ICL_TM_ATE_UNSOLVABLE,
				TermBundleMembers::STATE_GIVEN_UP,
				TermBundleMembers::STATE_IN_BUNDLE,
				TermBundleMembers::STATE_DROPPED,
				'%' . $wpdb->esc_like( self::REASON_START ) . '%',
				$this->pageSize()
			)
		);

		if ( ! is_array( $rows ) || '' !== (string) $wpdb->last_error ) {
			return false;
		}

		if ( ! $rows ) {
			return true;
		}

		$delivered = [];
		$empty     = [];
		foreach ( $rows as $row ) {
			if ( (int) $row->applied > 0 ) {
				$delivered[] = (int) $row->rid;
			} else {
				$empty[] = (int) $row->rid;
			}
		}

		if ( false === $this->setStatus( $delivered, ICL_TM_COMPLETE, false ) || false === $this->setStatus( $empty, ICL_TM_ATE_CANCELLED, false ) ) {
			return false;
		}

		$this->log( 'name_taken_term_bundles_closed', [ 'job_ids' => array_map( 'intval', array_column( $rows, 'job_id' ) ) ] );

		return count( $rows ) < $this->pageSize();
	}

	private function setStatus( array $rids, $status, $count = true ) {
		if ( ! $rids ) {
			return 0;
		}

		$wpdb = $this->db;

		$written = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}icl_translation_status SET status = %d
				  WHERE status = %d AND rid IN ( " . implode( ',', array_fill( 0, count( $rids ), '%d' ) ) . ' )',
				array_merge( [ (int) $status, ICL_TM_ATE_UNSOLVABLE ], $rids )
			)
		);

		if ( false !== $written && $count ) {
			$this->reopened += (int) $written;
		}

		return $written;
	}

	private function hasBundleMembersTable() {
		$wpdb = $this->db;

		$suppressed = $wpdb->suppress_errors( true );
		$wpdb->get_var( "SELECT 1 FROM {$wpdb->prefix}icl_term_batch_members LIMIT 1" );
		$exists = '' === (string) $wpdb->last_error;
		$wpdb->suppress_errors( $suppressed );

		return $exists;
	}

	private function log( $event, array $data ) {
		if ( class_exists( JobLog::class ) ) {
			JobLog::add( $event, $data );
		}
	}
}
