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
		string $xliff
	): WPML_TM_ATE_Models_Job_Create {
		$job                        = new WPML_TM_ATE_Models_Job_Create();
		$job->id                    = $jobId;
		$job->source_id             = $rid;
		$job->element_id            = $termTaxonomyId;
		$job->source_language->code = $sourceLang;
		$job->source_language->name = $this->taxonomyLanguageNameFor( $sourceLang );
		$job->target_language->code = $targetLang;
		$job->target_language->name = $this->taxonomyLanguageNameFor( $targetLang );
		$job->deadline              = 0;
		$job->apply_memory          = true;
		$job->permalink             = '#';
		$job->tier                  = (string) \WPML\Core\Component\Translation\Domain\Priority\Tier::TAXONOMY_TERMS;
		$job->rank                  = [ $this->taxonomyTermDepth( $term ), $termTaxonomyId ];
		$job->notify_enabled        = true;
		$job->notify_url            = \WPML\TM\ATE\REST\PublicReceive::get_receive_ate_job_url( $jobId );
		$job->site_identifier       = wpml_get_site_id( WPML_TM_ATE::SITE_ID_SCOPE );
		$job->job_sender            = \WPML\TM\ATE\JobSender\JobSenderRepository::get();

		( new TermJobRowFactory( $GLOBALS['wpdb'] ) )->fillBillingColumns(
			$job,
			$rid,
			$jobId,
			$this->countTaxonomyTermWords( $term )
		);

		$job->file->type = 'data:application/x-xliff;base64';
		$job->file->name = $term->name;
		$job->file->content = base64_encode( $xliff );

		return $job;
	}

	private function taxonomyTermDepth( \WP_Term $term ): int {
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

	protected function countTaxonomyTermWords( \WP_Term $term ): int {
		$metaText = \WPML\TM\Taxonomy\TranslatableTermMeta::countableText( \WPML\TM\Taxonomy\TranslatableTermMeta::values( $term ) );
		$text     = trim(
			wp_strip_all_tags(
				$term->name . ' ' . (string) $term->description . ( '' !== $metaText ? ' ' . $metaText : '' )
			)
		);

		return '' === $text ? 0 : count( preg_split( '/\s+/u', $text ) ?: [] );
	}
}
