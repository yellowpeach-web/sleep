<?php

namespace WPML\TM\Taxonomy\Job;

use WPML_TM_ATE;
use WPML_TM_ATE_Models_Job_Create;

trait BuildsTaxonomyTermJobModel {

	protected function buildTaxonomyJobModel(
		int $jobId,
		int $rid,
		int $termTaxonomyId,
		\WP_Term $term,
		string $sourceLang,
		string $targetLang,
		string $xliff,
		array $billing
	): WPML_TM_ATE_Models_Job_Create {
		$job = $this->newTermJobModel( $jobId, $rid, $termTaxonomyId, $sourceLang, $targetLang, [ $this->taxonomyTermDepth( $term ), $termTaxonomyId ], $term->name, $xliff );

		$fullCount = $this->countTaxonomyTermWords( $term, $sourceLang );
		( new TermJobRowFactory( $GLOBALS['wpdb'] ) )->fillBillingColumns(
			$job,
			$rid,
			$jobId,
			$fullCount,
			(int) ( $billing['words'] ?? $fullCount )
		);
		$job->apply_memory = empty( $billing['inFull'] );

		return $job;
	}

	protected function buildTermBundleJobModel(
		int $jobId,
		int $rid,
		int $batchId,
		array $members,
		string $sourceLang,
		string $targetLang,
		string $xliff,
		string $fileName
	): WPML_TM_ATE_Models_Job_Create {
		$rank           = null;
		$fullCountByRid = [];
		$billedByRid    = [];
		$inFull         = false;
		foreach ( $members as $member ) {
			$memberRank = [ $this->taxonomyTermDepth( $member['term'] ), (int) $member['term']->term_taxonomy_id ];
			if ( null === $rank || $memberRank < $rank ) {
				$rank = $memberRank;
			}
			$fullCountByRid[ (int) $member['rid'] ] = (int) $member['words'];
			$billedByRid[ (int) $member['rid'] ]    = (int) ( $member['billed_words'] ?? 0 );
			$inFull                                 = $inFull || ! empty( $member['billed_in_full'] );
		}

		$job = $this->newTermJobModel( $jobId, $rid, $batchId, $sourceLang, $targetLang, $rank ?: [ 0, 0 ], $fileName, $xliff );
		( new TermJobRowFactory( $GLOBALS['wpdb'] ) )->fillBundleBillingColumns( $job, $jobId, $billedByRid, $fullCountByRid );
		$job->apply_memory = ! $inFull;

		return $job;
	}

	private function newTermJobModel( int $jobId, int $rid, int $elementId, string $sourceLang, string $targetLang, array $rank, string $fileName, string $xliff ): WPML_TM_ATE_Models_Job_Create {
		$job                        = new WPML_TM_ATE_Models_Job_Create();
		$job->id                    = $jobId;
		$job->source_id             = $rid;
		$job->element_id            = $elementId;
		$job->source_language->code = $sourceLang;
		$job->source_language->name = $this->taxonomyLanguageNameFor( $sourceLang );
		$job->target_language->code = $targetLang;
		$job->target_language->name = $this->taxonomyLanguageNameFor( $targetLang );
		$job->deadline              = 0;
		$job->apply_memory          = true;
		$job->permalink             = '#';
		$job->tier                  = (string) \WPML\Core\Component\Translation\Domain\Priority\Tier::TAXONOMY_TERMS;
		$job->rank                  = $rank;
		$job->notify_enabled        = true;
		$job->notify_url            = \WPML\TM\ATE\REST\PublicReceive::get_receive_ate_job_url( $jobId );
		$job->receive_token_kind    = \WPML\TM\ATE\REST\PublicReceive::receive_token_kind( $jobId );
		$job->site_identifier       = wpml_get_site_id( WPML_TM_ATE::SITE_ID_SCOPE );
		$job->job_sender            = \WPML\TM\ATE\JobSender\JobSenderRepository::get();

		$job->file->type = 'data:application/x-xliff;base64';
		$job->file->name = $fileName;
		$job->file->content = base64_encode( $xliff );

		return $job;
	}

	protected function taxonomyTermDepth( \WP_Term $term ): int {
		if ( ! is_taxonomy_hierarchical( $term->taxonomy ) ) {
			return 0;
		}

		return count( get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) );
	}

	protected function resolveTaxonomyTermSource( $wpdb, int $termTaxonomyId, string $taxonomy ) {
		$term = self::withoutTermAdjustId(
			function () use ( $termTaxonomyId, $taxonomy ) {
				return get_term_by( 'term_taxonomy_id', $termTaxonomyId, $taxonomy );
			}
		);
		if ( ! $term instanceof \WP_Term || (int) $term->term_taxonomy_id !== $termTaxonomyId ) {
			return null;
		}

		$elementType = 'tax_' . $taxonomy;

		$source = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT translation_id, trid FROM {$wpdb->prefix}icl_translations
				WHERE element_id = %d AND element_type = %s AND source_language_code IS NULL",
				$termTaxonomyId,
				$elementType
			),
			ARRAY_A
		);
		if ( ! $source ) {
			return null;
		}

		return [ $term, (int) $source['trid'] ];
	}

	protected static function withoutTermAdjustId( callable $lookup ) {
		$disable = '__return_true';
		add_filter( 'wpml_disable_term_adjust_id', $disable, 999 );
		try {
			return $lookup();
		} finally {
			remove_filter( 'wpml_disable_term_adjust_id', $disable, 999 );
		}
	}

	protected function taxonomyLanguageNameFor( string $code ): string {
		$active = (array) apply_filters( 'wpml_active_languages', [] );

		return isset( $active[ $code ]['display_name'] ) ? (string) $active[ $code ]['display_name'] : $code;
	}

	protected function countTaxonomyTermWords( \WP_Term $term, ?string $sourceLang = null ): int {
		return TermWords::countTerm( $term, $sourceLang );
	}
}
