<?php

use WPML\TM\ATE\TranslateEverything\TaxonomyTermJobApplier;

class WPML_TP_Apply_Translation_Taxonomy_Strategy implements WPML_TP_Apply_Translation_Strategy {
	private $jobs_api;
	private $applier;
	private $wpdb;
	private $sitepress;

	public function __construct( WPML_TP_Jobs_API $jobs_api, wpdb $wpdb, ?SitePress $sitepress = null, ?TaxonomyTermJobApplier $applier = null ) {
		$this->jobs_api  = $jobs_api;
		$this->wpdb      = $wpdb;
		$this->sitepress = $sitepress;
		$this->applier   = $applier;
	}

	public function apply( WPML_TM_Job_Entity $job, $translations ) {
		if ( ! is_string( $translations ) || '' === $translations ) {
			throw new RuntimeException( 'The taxonomy translation is not raw XLIFF.' );
		}
		if ( ! $this->applier && ! $this->sitepress ) {
			throw new RuntimeException( 'The taxonomy term applier is unavailable.' );
		}
		$applier = $this->applier ?: new TaxonomyTermJobApplier( $this->wpdb, $this->sitepress );
		$result  = $applier->apply( $translations, $job->get_translate_job_id() );
		if ( WPML_TM_ATE_Jobs::SKIPPED_NOT_AWAITING_DELIVERY !== $result && ( ! $result || TaxonomyTermJobApplier::lastDeliveryGivenUp() ) ) {
			throw new WPML_TP_API_Exception( esc_html( TaxonomyTermJobApplier::lastFailureReason() ) ?: 'The taxonomy translation could not be applied.' );
		}

		$this->jobs_api->update_job_state( $job, 'delivered' );
	}
}
