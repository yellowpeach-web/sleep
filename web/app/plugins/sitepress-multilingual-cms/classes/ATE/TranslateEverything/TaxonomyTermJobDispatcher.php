<?php

namespace WPML\TM\ATE\TranslateEverything;

use WPML\Core\Component\Translation\Application\Service\Taxonomy\TaxonomyTermDispatchResult;
use WPML\Legacy\Component\Translation\Sender\Taxonomy\PreparedTaxonomyTermJob;
use WPML\Legacy\Component\Translation\Sender\Taxonomy\TaxonomyTermJobCleanup;
use WPML\Legacy\Component\Translation\Sender\Taxonomy\TaxonomyRemoteAteJobIdClaim;
use WPML\LIB\WP\User;
use WPML\Setup\Option;
use WPML\TM\ATE\JobRecords;
use WPML\TM\ATE\Review\TermJob;
use WPML\TM\ATE\Review\ReviewStatus;
use WPML\FP\Obj;
use WPML\TM\Taxonomy\Job\BuildsTaxonomyTermJobModel;
use WPML\TM\XLIFF\TaxonomyTermXliffBuilder;
use WPML\TM\XLIFF\TaxonomyTermXliffReader;
use WPML_TM_ATE;
use WPML_TM_ATE_API;
use WPML_TM_ATE_Models_Job_Create;
use WPML_TM_Editors;
use WPML\Translation\PreviousStateServiceFactory;

class TaxonomyTermJobDispatcher {

	use BuildsTaxonomyTermJobModel;

	const ATE_BATCH_SIZE = 20;

	const ATE_RECORD_ACTIVE_STATUSES = [ 0, 1, 6, 7 ];

	const REFUSED_UP_TO_DATE         = 'up_to_date';
	const REFUSED_BEING_TRANSLATED   = 'being_translated';
	const REFUSED_REVIEW_OPEN        = 'review_open';
	const REFUSED_DISPATCH_UNCERTAIN = 'dispatch_uncertain';

	const UNCERTAIN_OPTION_PREFIX = 'wpml.taxonomy_term_dispatch_uncertain_';

	const SENT_UNITS_META_PREFIX = '_wpml_tax_sent_units_';

	private $wpdb;

	private $xliff_builder;

	private $user_id;

	private $created_by_translate_everything = false;

	private $prepared_snapshots = [];

	private $last_refusal = null;

	public function __construct( \wpdb $wpdb, ?TaxonomyTermXliffBuilder $xliffBuilder = null, ?int $userId = null, bool $createdByTranslateEverything = false ) {
		$this->wpdb                            = $wpdb;
		$this->xliff_builder                   = $xliffBuilder ?: new TaxonomyTermXliffBuilder();
		$this->user_id                         = null === $userId ? (int) User::getCurrentId() : $userId;
		$this->created_by_translate_everything = $createdByTranslateEverything;
	}

	public function prepareTermJob(
		int $termTaxonomyId,
		string $taxonomy,
		string $sourceLang,
		string $targetLang,
		bool $overwrite,
		bool $freshGeneration = false
	) {
		$prepared = $this->prepareTermJobWithRecord(
			$termTaxonomyId,
			$taxonomy,
			$sourceLang,
			$targetLang,
			$overwrite,
			$freshGeneration
		);

		return $prepared ? $prepared->getModel() : null;
	}

	public function prepareTermJobWithRecord(
		int $termTaxonomyId,
		string $taxonomy,
		string $sourceLang,
		string $targetLang,
		bool $overwrite,
		bool $freshGeneration = false
	) {
		$wpdb               = $this->wpdb;
		$this->last_refusal = null;

		$source = $this->resolveTaxonomyTermSource( $wpdb, $termTaxonomyId, $taxonomy );
		if ( null === $source ) {
			return null;
		}
		list( $term, $trid ) = $source;

		$elementType = 'tax_' . $taxonomy;

		$targetTranslationCreated = false;
		$targetTranslationId      = $this->ensureTargetTranslationRow(
			$trid,
			$elementType,
			$targetLang,
			$sourceLang,
			$targetTranslationCreated
		);
		if ( ! $targetTranslationId ) {
			return null;
		}

		$statusBefore = [];
		$rid          = $this->ensureTranslationStatusRow( $targetTranslationId, $overwrite, $statusBefore, $term );
		if ( ! $rid ) {
			return null;
		}
		if ( $this->reconcileUncertainRid( $rid ) ) {
			return null;
		}

		$insertedJobId = 0;
		$jobId         = $this->ensureTranslateJobRow( $rid, $term->name, $insertedJobId, $freshGeneration );
		if ( ! $jobId ) {
			return null;
		}
		if ( $this->isDispatchUncertain( $jobId ) ) {
			$this->last_refusal = self::REFUSED_DISPATCH_UNCERTAIN;

			return null;
		}

		if ( ! $targetTranslationCreated ) {
			$this->writeTargetTranslationRow( $trid, $elementType, $targetLang, $sourceLang );
		}

		try {
			$xliff = $this->xliff_builder->build( $term, $sourceLang, $targetLang );
		} catch ( \Throwable $e ) {
			$this->releasePreparedJobs( [
				new PreparedTaxonomyTermJob(
					null,
					$termTaxonomyId,
					$taxonomy,
					$sourceLang,
					$targetLang,
					$targetTranslationId,
					$rid,
					$jobId,
					$insertedJobId,
					$statusBefore
				),
			] );
			return null;
		}

		$model = $this->buildTaxonomyJobModel( $jobId, $rid, $termTaxonomyId, $term, $sourceLang, $targetLang, $xliff );
		$this->prepared_snapshots[ $jobId ] = [
			'term'           => $term,
			'targetLanguage' => $targetLang,
			'units'          => TaxonomyTermXliffReader::sentUnitIds( $xliff ),
		];

		return new PreparedTaxonomyTermJob(
			$model,
			$termTaxonomyId,
			$taxonomy,
			$sourceLang,
			$targetLang,
			$targetTranslationId,
			$rid,
			$jobId,
			$insertedJobId,
			$statusBefore
		);
	}

	public function releasePreparedJobs( array $preparedJobs ): int {
		foreach ( $preparedJobs as $preparedJob ) {
			if ( $preparedJob instanceof PreparedTaxonomyTermJob ) {
				unset( $this->prepared_snapshots[ $preparedJob->getJobId() ] );
			}
		}

		return ( new TaxonomyTermJobCleanup( $this->wpdb ) )->release( $preparedJobs );
	}

	public function sendToAte( array $jobs, ?string &$error = null ): int {
		$result = $this->dispatchToAte( $jobs );
		$error  = $result->getErrorMessage();

		return $result->getAcceptedCount();
	}

	public function dispatchToAte( array $jobs ): TaxonomyTermDispatchResult {
		if ( ! $jobs ) {
			return new TaxonomyTermDispatchResult( [], [], [] );
		}

		try {
			$ateApi = \WPML\Container\make( WPML_TM_ATE_API::class );
		} catch ( \Throwable $e ) {
			$message = 'ATE API container resolution failed: ' . $e->getMessage();

			return new TaxonomyTermDispatchResult(
				[],
				[],
				array_values( $this->requestedJobsByRid( $jobs ) ),
				'ate_api_unavailable',
				$message
			);
		}

		$batches          = array_chunk( $jobs, self::ATE_BATCH_SIZE );
		$acceptedMappings = [];
		$blockedMappings  = [];
		$rejectedJobIds   = [];
		$uncertainJobIds  = [];

		foreach ( $batches as $index => $batch ) {
			$knownRemoteIds    = array_values( $acceptedMappings + $blockedMappings );
			$result            = $this->dispatchBatchToAte( $batch, $ateApi, $knownRemoteIds );
			$acceptedMappings += $result->getAcceptedMappings();
			$blockedMappings  += $result->getBlockedMappings();
			$rejectedJobIds    = array_merge( $rejectedJobIds, $result->getRejectedJobIds() );
			$uncertainJobIds   = array_merge( $uncertainJobIds, $result->getUncertainJobIds() );

			if ( $result->hasFailure() ) {
				$remainingBatches = array_slice( $batches, $index + 1 );
				$remainingJobs    = $remainingBatches ? array_merge( ...$remainingBatches ) : [];
				$remainingJobIds = array_values( $this->requestedJobsByRid( $remainingJobs ) );

				return new TaxonomyTermDispatchResult(
					$acceptedMappings,
					$blockedMappings,
					$rejectedJobIds,
					$result->getErrorCode(),
					$result->getErrorMessage(),
					$remainingJobIds,
					$uncertainJobIds
				);
			}
		}

		return new TaxonomyTermDispatchResult( $acceptedMappings, $blockedMappings, $rejectedJobIds, null, null, [], $uncertainJobIds );
	}

	private function dispatchBatchToAte( array $jobs, WPML_TM_ATE_API $ateApi, array $knownRemoteIds = [] ): TaxonomyTermDispatchResult {
		$requestedByRid = $this->requestedJobsByRid( $jobs );
		$requestedIds   = array_values( $requestedByRid );

		$encodedPayload = wp_json_encode(
			[
				'jobs'           => $jobs,
				'existing_jobs'  => [],
				'auto_translate' => true,
				'preview'        => (bool) Option::shouldBeReviewed(),
				'job_type'       => 'auto',
			]
		);
		if ( false === $encodedPayload ) {
			return new TaxonomyTermDispatchResult(
				[],
				[],
				$requestedIds,
				'ate_payload_encoding_failed',
				'Failed to encode ATE jobs payload.'
			);
		}
		$payload = json_decode( $encodedPayload, true );

		if ( ! $this->markDispatchUncertain( $requestedIds ) ) {
			return new TaxonomyTermDispatchResult(
				[],
				[],
				$requestedIds,
				'dispatch_identity_not_persisted',
				'Could not persist the taxonomy dispatch identity before contacting ATE.'
			);
		}

		try {
			$response = $ateApi->create_jobs( $payload );
		} catch ( \Throwable $e ) {
			return new TaxonomyTermDispatchResult(
				[],
				[],
				[],
				'ate_request_exception',
				$e->getMessage(),
				[],
				$requestedIds
			);
		}

		if ( is_wp_error( $response ) ) {
			return new TaxonomyTermDispatchResult(
				[],
				[],
				[],
				'ate_transport_error',
				$response->get_error_message(),
				[],
				$requestedIds
			);
		}

		$normalised = is_object( $response ) ? get_object_vars( $response ) : $response;
		if ( is_array( $normalised ) && isset( $normalised['jobs'] ) && is_object( $normalised['jobs'] ) ) {
			$normalised['jobs'] = get_object_vars( $normalised['jobs'] );
		}
		if ( ! is_array( $normalised ) || empty( $normalised['jobs'] ) || ! is_array( $normalised['jobs'] ) ) {
			return new TaxonomyTermDispatchResult(
				[],
				[],
				[],
				'ate_response_missing_jobs',
				'ATE response did not contain a usable jobs map.',
				[],
				$requestedIds
			);
		}

		$mappings = $this->validatedMappings( $normalised['jobs'], $requestedByRid, $knownRemoteIds );
		if ( null === $mappings ) {
			return new TaxonomyTermDispatchResult(
				[],
				[],
				[],
				'ate_response_invalid_jobs_map',
				'ATE response contained an invalid or ambiguous jobs map.',
				[],
				$requestedIds
			);
		}

		if ( ! $mappings ) {
			return new TaxonomyTermDispatchResult(
				[],
				[],
				[],
				'ate_response_unmapped_jobs',
				'ATE response did not map any requested job.',
				[],
				$requestedIds
			);
		}

		if ( $this->mappingsConflictWithStoredRows( $mappings ) ) {
			return new TaxonomyTermDispatchResult(
				[],
				[],
				[],
				'ate_response_identity_conflict',
				'ATE response conflicts with an existing translation job identity.',
				[],
				$requestedIds
			);
		}

		$jobRecords               = \WPML\Container\make( JobRecords::class );
		$persistedMappings        = [];
		$persistenceFailedJobIds = [];
		foreach ( $mappings as $wpmlJobId => $remoteJobId ) {
			if ( ! $this->storeAndVerifyRemoteMapping( $jobRecords, $wpmlJobId, $remoteJobId ) ) {
				$persistenceFailedJobIds[] = $wpmlJobId;

				continue;
			}

			$persistedMappings[ $wpmlJobId ] = $remoteJobId;
			$this->clearDispatchUncertain( $wpmlJobId );
			$this->storePreparedSourceSnapshot( $wpmlJobId );
		}

		$responseUnmappedIds = array_values( array_diff( $requestedIds, array_keys( $mappings ) ) );
		$uncertainIds        = array_values( array_unique( array_merge( $responseUnmappedIds, $persistenceFailedJobIds ) ) );
		$responseCode         = $this->responseErrorCode( $normalised );
		if ( null !== $responseCode ) {
			$message = $this->responseErrorMessage( $normalised, $responseCode );

			return new TaxonomyTermDispatchResult( [], $persistedMappings, [], $responseCode, $message, [], $uncertainIds );
		}

		$acceptedIds = array_map( 'intval', array_keys( $persistedMappings ) );
		TermJob::flagAcceptedJobs( $acceptedIds );
		if ( $persistenceFailedJobIds ) {
			return new TaxonomyTermDispatchResult(
				$persistedMappings,
				[],
				[],
				'ate_mapping_persistence_failed',
				'ATE accepted taxonomy jobs whose remote identity could not be verified locally.',
				[],
				$uncertainIds
			);
		}

		return new TaxonomyTermDispatchResult(
			$persistedMappings,
			[],
			[],
			$responseUnmappedIds ? 'ate_partial_acceptance' : null,
			$responseUnmappedIds ? 'ATE returned mappings for only part of the requested taxonomy jobs.' : null,
			[],
			$responseUnmappedIds
		);
	}

	private function storeAndVerifyRemoteMapping( JobRecords $jobRecords, int $wpmlJobId, int $remoteJobId ): bool {
		$claim = new TaxonomyRemoteAteJobIdClaim( $this->wpdb );
		if ( ! $claim->acquire( $wpmlJobId, $remoteJobId ) ) {
			return false;
		}

		if ( $this->mappingsConflictWithStoredRows( [ $wpmlJobId => $remoteJobId ] ) ) {
			return false;
		}

		$wpdb = $this->wpdb;
		$wpdb->update(
			$wpdb->prefix . 'icl_translate_job',
			[
				'editor'        => \WPML_TM_Editors::ATE,
				'editor_job_id' => $remoteJobId,
			],
			[ 'job_id' => $wpmlJobId ],
			[ '%s', '%d' ],
			[ '%d' ]
		);

		try {
			$jobRecords->store( $wpmlJobId, [ JobRecords::FIELD_ATE_JOB_ID => $remoteJobId ] );
		} catch ( \Throwable $e ) {
			return false;
		}

		$matchingRows = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}icl_translate_job
				 WHERE job_id = %d AND editor_job_id = %d AND editor = %s",
				$wpmlJobId,
				$remoteJobId,
				\WPML_TM_Editors::ATE
			)
		);

		if ( ! empty( $wpdb->last_error ) || 1 !== (int) $matchingRows ) {
			return false;
		}

		$claim->release( $wpmlJobId, $remoteJobId );

		return true;
	}

	private function validatedMappings( array $responseMappings, array $requestedByRid, array $knownRemoteIds ) {
		$mappings      = [];
		$seenRemoteIds = array_fill_keys( array_map( 'intval', $knownRemoteIds ), true );

		foreach ( $responseMappings as $ridValue => $remoteValue ) {
			$rid         = $this->canonicalPositiveInteger( $ridValue );
			$remoteJobId = $this->canonicalPositiveInteger( $remoteValue );
			if ( null === $rid || null === $remoteJobId || ! isset( $requestedByRid[ $rid ] ) ) {
				return null;
			}

			$wpmlJobId = $requestedByRid[ $rid ];
			if ( isset( $mappings[ $wpmlJobId ] ) || isset( $seenRemoteIds[ $remoteJobId ] ) ) {
				return null;
			}

			$mappings[ $wpmlJobId ]       = $remoteJobId;
			$seenRemoteIds[ $remoteJobId ] = true;
		}

		return $mappings;
	}

	private function mappingsConflictWithStoredRows( array $mappings ): bool {
		if ( ! $mappings ) {
			return false;
		}

		$localIds  = array_map( 'intval', array_keys( $mappings ) );
		$remoteIds = array_map( 'intval', array_values( $mappings ) );
		$wpdb      = $this->wpdb;
		$wpdb->last_error = '';
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT job_id, editor_job_id
				FROM {$wpdb->prefix}icl_translate_job
				WHERE job_id IN (" . implode( ',', array_fill( 0, count( $localIds ), '%d' ) ) . ')
				OR editor_job_id IN (' . implode( ',', array_fill( 0, count( $remoteIds ), '%d' ) ) . ')',
				array_merge( $localIds, $remoteIds )
			),
			ARRAY_A
		);
		if ( ! empty( $wpdb->last_error ) || ! is_array( $rows ) ) {
			return true;
		}

		$ownerByRemote = array_flip( $mappings );
		foreach ( $rows as $row ) {
			$jobId      = (int) ( $row['job_id'] ?? 0 );
			$remoteJobId = (int) ( $row['editor_job_id'] ?? 0 );
			if ( isset( $mappings[ $jobId ] ) && $remoteJobId > 0 && $mappings[ $jobId ] !== $remoteJobId ) {
				return true;
			}
			if ( $remoteJobId > 0 && isset( $ownerByRemote[ $remoteJobId ] ) && (int) $ownerByRemote[ $remoteJobId ] !== $jobId ) {
				return true;
			}
		}

		return false;
	}

	private function canonicalPositiveInteger( $value ): ?int {
		if ( is_int( $value ) ) {
			return $value > 0 ? $value : null;
		}

		if ( ! is_string( $value ) || ! preg_match( '/^[1-9][0-9]*$/D', $value ) ) {
			return null;
		}

		$maximum = (string) PHP_INT_MAX;
		if ( strlen( $value ) > strlen( $maximum ) || ( strlen( $value ) === strlen( $maximum ) && strcmp( $value, $maximum ) > 0 ) ) {
			return null;
		}

		return (int) $value;
	}

	private function canonicalNonNegativeInteger( $value ): ?int {
		if ( is_int( $value ) ) {
			return $value >= 0 ? $value : null;
		}

		if ( ! is_string( $value ) || ! preg_match( '/^(0|[1-9][0-9]*)$/D', $value ) ) {
			return null;
		}

		$maximum = (string) PHP_INT_MAX;
		if ( strlen( $value ) > strlen( $maximum ) || ( strlen( $value ) === strlen( $maximum ) && strcmp( $value, $maximum ) > 0 ) ) {
			return null;
		}

		return (int) $value;
	}

	private function markDispatchUncertain( array $jobIds ): bool {
		foreach ( $jobIds as $jobId ) {
			update_option( self::UNCERTAIN_OPTION_PREFIX . (int) $jobId, time(), false );
			if ( ! $this->isDispatchUncertain( (int) $jobId ) ) {
				return false;
			}
		}

		return true;
	}

	private function clearDispatchUncertain( int $jobId ): void {
		delete_option( self::UNCERTAIN_OPTION_PREFIX . $jobId );
	}

	private function isDispatchUncertain( int $jobId ): bool {
		return false !== get_option( self::UNCERTAIN_OPTION_PREFIX . $jobId, false );
	}

	private function requestedJobsByRid( array $jobs ): array {
		$requested = [];
		foreach ( $jobs as $job ) {
			if ( ! is_object( $job ) || ! isset( $job->id, $job->source_id ) ) {
				continue;
			}

			$jobId = (int) $job->id;
			$rid   = (int) $job->source_id;
			if ( $jobId > 0 && $rid > 0 ) {
				$requested[ $rid ] = $jobId;
			}
		}

		return $requested;
	}

	private function responseErrorCode( array $response ) {
		if ( ! array_key_exists( 'code', $response ) || null === $response['code'] || '' === $response['code'] ) {
			return null;
		}

		$code = (string) $response['code'];

		return '0' === $code || '200' === $code ? null : $code;
	}

	private function responseErrorMessage( array $response, string $fallback ): string {
		foreach ( [ 'message', 'error', 'status' ] as $key ) {
			if ( isset( $response[ $key ] ) && is_scalar( $response[ $key ] ) && '' !== (string) $response[ $key ] ) {
				return (string) $response[ $key ];
			}
		}

		return 'ATE rejected the taxonomy jobs with code ' . $fallback . '.';
	}

	private function storePreparedSourceSnapshot( int $jobId ): void {
		if ( ! isset( $this->prepared_snapshots[ $jobId ] ) ) {
			return;
		}

		$prepared = $this->prepared_snapshots[ $jobId ];
		$this->storeSourceSnapshot( $prepared['term'], $prepared['targetLanguage'] );
		if ( function_exists( 'update_term_meta' ) ) {
			update_term_meta( (int) $prepared['term']->term_id, self::SENT_UNITS_META_PREFIX . $prepared['targetLanguage'], $prepared['units'] );
		}
		unset( $this->prepared_snapshots[ $jobId ] );
	}

	private function ensureTargetTranslationRow(
		int $trid,
		string $elementType,
		string $targetLang,
		string $sourceLang,
		bool &$created
	): int {
		$wpdb = $this->wpdb;
		$created = false;

		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT translation_id FROM {$wpdb->prefix}icl_translations
				WHERE trid = %d AND language_code = %s",
				$trid,
				$targetLang
			),
			ARRAY_A
		);
		if ( $existing ) {
			return (int) $existing['translation_id'];
		}

		$translationId = $this->writeTargetTranslationRow( $trid, $elementType, $targetLang, $sourceLang );
		$created       = $translationId > 0;

		return $translationId;
	}

	private function writeTargetTranslationRow( int $trid, string $elementType, string $targetLang, string $sourceLang ): int {
		global $sitepress;

		return (int) $sitepress->set_element_language_details( null, $elementType, $trid, $targetLang, $sourceLang );
	}

	private function storeSourceSnapshot( \WP_Term $term, string $targetLang ) {
		if ( ! function_exists( 'update_term_meta' ) ) {
			return;
		}

		$prefix = class_exists( \WPML\TM\Dashboard\Taxonomy\TaxonomyDashboardData::class )
			? \WPML\TM\Dashboard\Taxonomy\TaxonomyDashboardData::WC_SNAPSHOT_META_PREFIX
			: '_wpml_tax_wc_src_';

		$metaText = \WPML\TM\Taxonomy\TranslatableTermMeta::metaText( $term );
		$source   = trim(
			wp_strip_all_tags(
				$term->name . "\n" . ( $term->description ?? '' ) . ( '' !== $metaText ? "\n" . $metaText : '' )
			)
		);

		update_term_meta( (int) $term->term_id, $prefix . $targetLang, $source );
	}

	public function getLastRefusal() {
		return $this->last_refusal;
	}

	private function ensureTranslationStatusRow( int $translationId, bool $overwrite, array &$before, ?\WP_Term $term = null ): int {
		$wpdb = $this->wpdb;
		$before = [];

		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT rid, status, review_status, needs_update, md5, translation_service, translator_id FROM {$wpdb->prefix}icl_translation_status WHERE translation_id = %d",
				$translationId
			),
			ARRAY_A
		);

		if ( $existing ) {
			$currentStatus = (int) $existing['status'];

			if ( ReviewStatus::EDITING === Obj::prop( 'review_status', $existing ) ) {
				$this->last_refusal = self::REFUSED_REVIEW_OPEN;

				return 0;
			}

			if ( (int) ICL_TM_COMPLETE === $currentStatus && ! $overwrite && 0 === (int) ( $existing['needs_update'] ?? 0 ) ) {
				$this->last_refusal = self::REFUSED_UP_TO_DATE;

				return 0;
			}

			if ( ! $overwrite && (int) ICL_TM_IN_PROGRESS === $currentStatus && $this->ridHasCurrentRemoteJob( (int) $existing['rid'] ) ) {
				$this->last_refusal = self::REFUSED_BEING_TRANSLATED;

				return 0;
			}

			$before = [
				'created'      => false,
				'status'       => $currentStatus,
				'needs_update' => (int) ( $existing['needs_update'] ?? 0 ),
				'service'      => (string) ( $existing['translation_service'] ?? '' ),
				'translator'   => (int) ( $existing['translator_id'] ?? 0 ),
			];
			PreviousStateServiceFactory::create()->update(
				$translationId,
				[
					'status'              => $currentStatus,
					'needs_update'        => (bool) $before['needs_update'],
					'md5'                 => (string) ( $existing['md5'] ?? '' ),
					'translation_service' => $before['service'],
					'translator_id'       => $before['translator'],
				]
			);
			$wpdb->update(
				$wpdb->prefix . 'icl_translation_status',
				[
					'status'              => ICL_TM_IN_PROGRESS,
					'translation_service' => 'local',
					'translator_id'       => 0,
					'needs_update'        => 0,
				],
				[ 'translation_id' => $translationId ],
				[ '%d', '%s', '%d', '%d' ],
				[ '%d' ]
			);
			return (int) $existing['rid'];
		}

		$wpdb->insert(
			$wpdb->prefix . 'icl_translation_status',
			[
				'translation_id'      => $translationId,
				'status'              => ICL_TM_IN_PROGRESS,
				'translation_service' => 'local',
				'translator_id'       => 0,
				'batch_id'            => 0,
				'needs_update'        => 0,
				'md5'                 => $term ? $this->xliff_builder->sourceChecksum( $term ) : '',
				'translation_package' => '',
			],
			[ '%d', '%d', '%s', '%d', '%d', '%d', '%s', '%s' ]
		);
		$before = [ 'created' => true ];

		return (int) $wpdb->insert_id;
	}

	private function ensureTranslateJobRow( int $rid, string $title, int &$insertedJobId, bool $freshGeneration = false ): int {
		$factory       = ( new \WPML\TM\Taxonomy\Job\TermJobRowFactory( $this->wpdb, $this->user_id ) )
			->withProvenance( $this->created_by_translate_everything, \WPML\Setup\Option::getReviewMode() );
		$jobId         = $factory->resolveJobRow( $rid, $title, ! $freshGeneration );
		$insertedJobId = $factory->getInsertedJobId();

		return $jobId;
	}

	private function uncertainCurrentJob( int $rid ) {
		$wpdb = $this->wpdb;
		$row  = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT job_id, editor_job_id, translated
				 FROM {$wpdb->prefix}icl_translate_job
				 WHERE rid = %d AND revision IS NULL
				 ORDER BY job_id DESC LIMIT 1",
				$rid
			),
			ARRAY_A
		);
		if ( ! is_array( $row ) ) {
			return empty( $wpdb->last_error ) ? null : false;
		}
		if ( ! $this->isDispatchUncertain( (int) $row['job_id'] ) ) {
			return null;
		}

		return $row;
	}

	private function reconcileUncertainRid( int $rid ): bool {
		$row = $this->uncertainCurrentJob( $rid );
		if ( false === $row ) {
			$this->last_refusal = self::REFUSED_DISPATCH_UNCERTAIN;

			return true;
		}
		if ( null === $row ) {
			return false;
		}

		$jobId = (int) $row['job_id'];
		if ( ! empty( $row['translated'] ) ) {
			$this->clearDispatchUncertain( $jobId );

			return false;
		}
		if ( (int) $row['editor_job_id'] > 0 ) {
			$this->clearDispatchUncertain( (int) $row['job_id'] );
			$this->last_refusal = self::REFUSED_BEING_TRANSLATED;

			return true;
		}

		try {
			$ateApi   = \WPML\Container\make( WPML_TM_ATE_API::class );
			$response = $ateApi->get_jobs_by_wpml_ids( [ $rid ] );
		} catch ( \Throwable $e ) {
			$this->last_refusal = self::REFUSED_DISPATCH_UNCERTAIN;

			return true;
		}

		if ( is_wp_error( $response ) || ! is_object( $response ) ) {
			$this->last_refusal = self::REFUSED_DISPATCH_UNCERTAIN;

			return true;
		}

		$properties = get_object_vars( $response );
		if ( ! array_key_exists( (string) $rid, $properties ) ) {
			if ( $properties ) {
				$this->last_refusal = self::REFUSED_DISPATCH_UNCERTAIN;

				return true;
			}

			$this->clearDispatchUncertain( $jobId );

			return false;
		}

		$entry       = is_object( $properties[ $rid ] ) ? get_object_vars( $properties[ $rid ] ) : $properties[ $rid ];
		$remoteJobId = is_array( $entry ) && array_key_exists( 'ate_job_id', $entry )
			? $this->canonicalPositiveInteger( $entry['ate_job_id'] )
			: null;
		$hasMaker    = is_array( $entry ) && array_key_exists( 'wpml_job_id', $entry );
		$echoedJobId = $hasMaker ? $this->canonicalPositiveInteger( $entry['wpml_job_id'] ) : null;
		$hasStatus   = is_array( $entry ) && ( array_key_exists( 'status_id', $entry ) || array_key_exists( 'status', $entry ) );
		$statusValue = is_array( $entry ) && array_key_exists( 'status_id', $entry ) ? $entry['status_id'] : ( $entry['status'] ?? null );
		$ateStatus   = $hasStatus ? $this->canonicalNonNegativeInteger( $statusValue ) : null;
		$holderJobId = null !== $remoteJobId && ( ! $hasMaker || ( null !== $echoedJobId && $echoedJobId !== $jobId ) )
			? $this->previousGenerationHolder( $rid, $jobId, $remoteJobId, $echoedJobId )
			: null;
		if ( null !== $holderJobId ) {
			if ( null !== $ateStatus && in_array( $ateStatus, self::ATE_RECORD_ACTIVE_STATUSES, true ) ) {
				( new \WPML\TM\Taxonomy\Job\TermJobRowFactory( $this->wpdb, $this->user_id ) )
					->cancelSupersededRemoteJob( (int) $remoteJobId, $holderJobId, $rid );
			}
			$this->clearDispatchUncertain( $jobId );

			return false;
		}
		if (
			null === $remoteJobId
			|| ! $hasMaker
			|| null === $echoedJobId
			|| $echoedJobId !== $jobId
			|| ! $hasStatus
			|| null === $ateStatus
			|| ! in_array( $ateStatus, self::ATE_RECORD_ACTIVE_STATUSES, true )
			|| ! $this->isNewestJobForRid( $jobId, $rid )
		) {
			$this->last_refusal = self::REFUSED_DISPATCH_UNCERTAIN;

			return true;
		}

		$mappings = [ $jobId => $remoteJobId ];
		if ( $this->mappingsConflictWithStoredRows( $mappings ) ) {
			$this->last_refusal = self::REFUSED_DISPATCH_UNCERTAIN;

			return true;
		}

		$jobRecords = \WPML\Container\make( JobRecords::class );
		if ( ! $this->storeAndVerifyRemoteMapping( $jobRecords, $jobId, $remoteJobId ) ) {
			$this->last_refusal = self::REFUSED_DISPATCH_UNCERTAIN;

			return true;
		}

		$this->clearDispatchUncertain( $jobId );
		$this->last_refusal = self::REFUSED_BEING_TRANSLATED;

		return true;
	}

	private function previousGenerationHolder( int $rid, int $jobId, int $remoteJobId, ?int $maker ) {
		$wpdb             = $this->wpdb;
		$wpdb->last_error = '';
		$holders = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT job_id, rid, translated, revision FROM {$wpdb->prefix}icl_translate_job
				 WHERE editor_job_id = %d LIMIT 2",
				$remoteJobId
			),
			ARRAY_A
		);
		if ( ! empty( $wpdb->last_error ) || ! is_array( $holders ) || 1 !== count( $holders ) ) {
			return null;
		}

		$holder      = $holders[0];
		$holderJobId = $this->canonicalPositiveInteger( $holder['job_id'] ?? null );

		$proven = null !== $holderJobId
			&& $holderJobId < $jobId
			&& $this->canonicalPositiveInteger( $holder['rid'] ?? null ) === $rid
			&& null !== ( $holder['revision'] ?? null )
			&& ( null === $maker || $maker === $holderJobId )
			&& $this->isNewestJobForRid( $jobId, $rid );

		return $proven ? $holderJobId : null;
	}

	private function isNewestJobForRid( int $jobId, int $rid ): bool {
		$wpdb = $this->wpdb;
		$newestJobId = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT job_id FROM {$wpdb->prefix}icl_translate_job
				 WHERE rid = %d ORDER BY job_id DESC LIMIT 1",
				$rid
			)
		);

		return empty( $wpdb->last_error ) && $this->canonicalPositiveInteger( $newestJobId ) === $jobId;
	}

	private function ridHasCurrentRemoteJob( int $rid ): bool {
		$wpdb = $this->wpdb;

		$editorJobId = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT editor_job_id FROM {$wpdb->prefix}icl_translate_job
				 WHERE rid = %d AND revision IS NULL
				 AND editor_job_id IS NOT NULL AND editor_job_id > 0
				 LIMIT 1",
				$rid
			)
		);

		return (int) $editorJobId > 0;
	}
}
