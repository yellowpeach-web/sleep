<?php

namespace WPML\TM\ATE\Review;

use WPML\TM\ATE\TranslateEverything\TermBundleMembers;

class TermReviewAcceptance {

	private $wpdb;

	private $members;

	public function __construct( ?\wpdb $wpdb = null, ?TermBundleMembers $members = null ) {
		$this->wpdb    = $wpdb ?: $GLOBALS['wpdb'];
		$this->members = $members ?: new TermBundleMembers( $this->wpdb );
	}

	public function accept( $termRid ) {
		$termRid  = (int) $termRid;
		$accepted = [];

		if ( $this->acceptRow( $termRid ) ) {
			$accepted[] = $termRid;
		}

		foreach ( $this->members->appliedBundlesOfTermRid( $termRid ) as $bundle ) {
			if (
				ReviewStatus::NEEDS_REVIEW === $bundle['review_status']
				&& $this->isOnlyDeliveredTerm( $bundle['batch_job_id'], $termRid )
				&& $this->acceptRow( $bundle['rid'] )
			) {
				$accepted[] = $bundle['rid'];
			}
		}

		if ( $accepted ) {
			ReviewStatusLookup::invalidate();
		}

		return $accepted;
	}

	private function isOnlyDeliveredTerm( int $batchJobId, int $termRid ): bool {
		$delivered = [ TermBundleMembers::STATE_APPLIED, TermBundleMembers::STATE_GIVEN_UP ];

		foreach ( $this->members->membersOf( $batchJobId ) as $member ) {
			if ( in_array( $member['state'], $delivered, true ) && $member['term_rid'] !== $termRid ) {
				return false;
			}
		}

		return true;
	}

	private function acceptRow( int $rid ): bool {
		$wpdb = $this->wpdb;

		$changed = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}icl_translation_status SET review_status = %s WHERE rid = %d AND review_status = %s",
				ReviewStatus::ACCEPTED,
				(int) $rid,
				ReviewStatus::NEEDS_REVIEW
			)
		);

		return (bool) $changed;
	}
}
