<?php

use WPML\TM\Emails\Report\JobRowsStorage;

class WPML_TM_Batch_Report {

	const BATCH_REPORT_OPTION = '_wpml_batch_report';

	private $blog_translators;

	private $wpdb;

	private $storage;

	public function __construct( WPML_TM_Blog_Translators $blog_translators, \wpdb $wpdb, ?JobRowsStorage $storage = null ) {
		$this->blog_translators = $blog_translators;
		$this->wpdb             = $wpdb;
		$this->storage          = $storage ?: new JobRowsStorage( $wpdb );
	}

	public function set_job( WPML_Translation_Job $job ) {
		$job_fields = $job->get_basic_data();
		if ( ! WPML_User_Jobs_Notification_Settings::is_new_job_notification_enabled( $job_fields->translator_id ) ) {
			return;
		}

		$this->storage->upsert(
			$job->get_id(),
			(int) $job_fields->translator_id,
			$job_fields->source_language_code . '|' . $job_fields->language_code,
			strtolower( $job->get_type() )
		);
	}

	public function get_unassigned_translators( $batch_jobs = null ) {
		$batch_jobs           = $batch_jobs ?: $this->get_jobs();
		$assigned_translators = array_keys( $batch_jobs );
		$blog_translators     = wp_list_pluck( $this->blog_translators->get_blog_translators() , 'ID');

		return array_diff( $blog_translators, $assigned_translators );
	}

	public function clean_batch_jobs()
	{
		global $sitepress;

		$rows = $this->storage->getAll();
		if ( empty( $rows ) ) {
			return;
		}

		$valid_language_codes = array_fill_keys( array_keys( $sitepress->get_active_languages() ), true );

		$dead_job_ids    = array();
		$numeric_job_ids = array();

		foreach ( $rows as $job_id => $row ) {
			$languages = explode( '|', (string) ( $row['lang_pair'] ?? '' ) );

			if ( count( $languages ) !== 2
				|| ! isset( $valid_language_codes[ $languages[0] ] )
				|| ! isset( $valid_language_codes[ $languages[1] ] )
			) {
				$dead_job_ids[] = $job_id;
				continue;
			}

			if ( ctype_digit( (string) $job_id ) ) {
				$numeric_job_ids[] = (int) $job_id;
			}
		}

		$dead_job_ids = array_merge( $dead_job_ids, $this->get_unemailable_job_ids( $numeric_job_ids ) );

		$this->storage->deleteByJobIds( $dead_job_ids );
	}

	private function get_unemailable_job_ids( array $job_ids )
	{
		$job_ids = array_unique( $job_ids );
		if ( empty( $job_ids ) ) {
			return array();
		}

		$found = array();
		foreach ( array_chunk( $job_ids, 500 ) as $chunk ) {
			$wpdb    = $this->wpdb;
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT job_id, automatic FROM {$wpdb->prefix}icl_translate_job WHERE job_id IN ("
					. implode( ', ', array_fill( 0, count( $chunk ), '%d' ) ) . ')',
					...array_map( 'intval', array_values( $chunk ) )
				)
			);

			foreach ( (array) $results as $row ) {
				$found[ (int) $row->job_id ] = (bool) $row->automatic;
			}
		}

		return array_values( array_filter(
			$job_ids,
			fn( $job_id ) => ! isset( $found[ $job_id ] ) || $found[ $job_id ]
		) );
	}

	private function validate_jobs_assignment( $translatorId, $languagePairName ) {
		if ( 0 === (int) $translatorId ) {
			return true;
		}

		if ( ! WPML_User_Jobs_Notification_Settings::is_new_job_notification_enabled( $translatorId ) ) {
			return false;
		}

		$languages = explode( '|', $languagePairName );
		if ( count( $languages ) !== 2 ) {
			return false;
		}

		$args = array(
			'lang_from' => $languages[0],
			'lang_to'   => $languages[1]
		);

		return $this->blog_translators->is_translator( $translatorId, $args );
	}

	public function get_jobs() {
		$wpdb = $this->wpdb;

		$rows         = $this->storage->getAll();
		$jobIds       = [];
		$filteredJobs = [];
		$manualJobs   = [];

		foreach ( $rows as $jobId => $row ) {
			$translatorId = (int) ( $row['translator_id'] ?? 0 );
			$langPair     = (string) ( $row['lang_pair'] ?? '' );

			if ( ! $this->validate_jobs_assignment( $translatorId, $langPair ) ) {
				continue;
			}

			$jobIds[] = (int) $jobId;

			$filteredJobs[ $translatorId ][ $langPair ][] = [
				'element_id' => null,
				'type'       => (string) ( $row['type'] ?? '' ),
				'job_id'     => ctype_digit( (string) $jobId ) ? (int) $jobId : $jobId,
			];
		}

		if ( empty( $jobIds ) ) {
			return [];
		}

		$jobsAutommaticStatus = $wpdb->get_results(
			$wpdb->prepare(
				"
				SELECT tj.job_id, tj.automatic, iclt.field_data AS element_id
				FROM {$wpdb->prefix}icl_translate_job tj
				LEFT JOIN {$wpdb->prefix}icl_translate iclt
					ON iclt.job_id = tj.job_id AND iclt.field_type = 'original_id'
				WHERE tj.job_id IN (" . implode( ', ', array_fill( 0, count( $jobIds ), '%d' ) ) . ")
				LIMIT %d
				",
				array_merge( $jobIds, [ count( $jobIds ) ] )
			),
			OBJECT_K
		);

		if ( empty( $jobsAutommaticStatus ) || ! is_array( $jobsAutommaticStatus ) ) {
			return $filteredJobs;
		}

		foreach ( $filteredJobs as $translatorId => $languagePairs ) {
			foreach ( $languagePairs as $languagePairName => $languagePairItems ) {
				$languagePairItems = array_filter( $languagePairItems, function( $languagePairItem ) use ( $jobsAutommaticStatus ) {
					if ( ! array_key_exists( $languagePairItem['job_id'] , $jobsAutommaticStatus ) ) {
						return false;
					}
					return (bool) $jobsAutommaticStatus[ $languagePairItem['job_id'] ]->automatic === false;
				} );

				if ( empty( $languagePairItems ) ) {
					continue;
				}

				$manualJobs[ $translatorId ][ $languagePairName ] = array_map(
					function( $languagePairItem ) use ( $jobsAutommaticStatus ) {
						$languagePairItem['element_id'] = $jobsAutommaticStatus[ $languagePairItem['job_id'] ]->element_id;
						return $languagePairItem;
					},
					array_values( $languagePairItems )
				);
			}
		}

		return $manualJobs;
	}

	public function remove_emailed_jobs( array $snapshot, array $translators_ids ) {
		$job_ids = array();

		foreach ( array_unique( $translators_ids ) as $translator_id ) {
			$language_pairs = isset( $snapshot[ $translator_id ] ) ? $snapshot[ $translator_id ] : array();
			foreach ( $language_pairs as $language_pair_items ) {
				foreach ( $language_pair_items as $item ) {
					$job_ids[] = $item['job_id'];
				}
			}
		}

		$this->storage->deleteByJobIds( $job_ids );
	}
}
