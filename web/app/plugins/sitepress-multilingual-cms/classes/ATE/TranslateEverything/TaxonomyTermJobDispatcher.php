<?php

namespace WPML\TM\ATE\TranslateEverything;

use WPML\Core\Component\ATE\Domain\SiteRegistration;
use WPML\Core\Component\Translation\Application\Service\Taxonomy\TaxonomyTermDispatchResult;
use WPML\Legacy\Component\Translation\Sender\Taxonomy\CarriedTermPairs;
use WPML\Legacy\Component\Translation\Sender\Taxonomy\PreparedTaxonomyTermJob;
use WPML\Legacy\Component\Translation\Sender\Taxonomy\PreparedTermBundle;
use WPML\Legacy\Component\Translation\Sender\Taxonomy\TaxonomyTermJobCleanup;
use WPML\Legacy\Component\Translation\Sender\Taxonomy\TaxonomyTermPairClaimRepository;
use WPML\Legacy\Component\Translation\Sender\Taxonomy\TaxonomyRemoteAteJobIdClaim;
use WPML\LIB\WP\User;
use WPML\Setup\Option;
use WPML\TM\ATE\AutomaticPairSupport;
use WPML\TM\ATE\ClonedSites\ApiCommunication;
use WPML\TM\ATE\JobRecords;
use WPML\TM\Jobs\JobLog;
use WPML\TM\ATE\Review\TermJob;
use WPML\TM\ATE\Review\ReviewStatus;
use WPML\FP\Obj;
use WPML\TM\Taxonomy\Job\BuildsTaxonomyTermJobModel;
use WPML\TM\Taxonomy\Job\TermWords;
use WPML\TM\Taxonomy\TranslatableTermMeta;
use WPML\TM\XLIFF\TaxonomyTermXliffBuilder;
use WPML\TM\XLIFF\TaxonomyTermXliffReader;
use WPML\TM\XLIFF\TermBundleXliffBuilder;
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
	const REFUSED_CARRIED_BY_POST    = 'carried_by_post';
	const REFUSED_DISPATCH_UNCERTAIN = 'dispatch_uncertain';

	const REFUSED_UNSUPPORTED_PAIR = 'unsupported_pair';

	const UNCERTAIN_OPTION_PREFIX = 'wpml.taxonomy_term_dispatch_uncertain_';
	const TP_RECONCILIATION_ATTEMPTS = 3;

	const ERROR_REFUSED_BEFORE_REQUEST = 'ate_request_refused_locally';

	const REFUSED_BEFORE_REQUEST_CODES = [
		SiteRegistration::NOT_REGISTERED_ERROR_CODE,
		ApiCommunication::RECONNECTING_ERROR_CODE,
	];

	const RESEND_BOUND   = 'bound';
	const RESEND_ADOPTED = 'adopted';
	const RESEND_PARKED  = 'parked';
	const RESEND_HELD    = 'held';
	const RESEND_RETIRED = 'retired';

	const SENT_UNITS_META_PREFIX = '_wpml_tax_sent_units_';

	private $wpdb;

	private $xliff_builder;

	private $bundle_xliff_builder;

	private $bundle_members;

	private $user_id;

	private $created_by_translate_everything = false;

	private $sent_from_retry = false;

	private $prepared_snapshots = [];

	private $prepared_members = [];

	private $last_refusal = null;

	private $last_target_element_id = null;

	private $last_carrying_post_id = null;

	public function __construct( \wpdb $wpdb, ?TaxonomyTermXliffBuilder $xliffBuilder = null, ?int $userId = null, bool $createdByTranslateEverything = false, ?TermBundleXliffBuilder $bundleXliffBuilder = null, bool $sentFromRetry = false ) {
		$this->wpdb                            = $wpdb;
		$this->xliff_builder                   = $xliffBuilder ?: new TaxonomyTermXliffBuilder();
		$this->bundle_xliff_builder            = $bundleXliffBuilder ?: new TermBundleXliffBuilder( $this->xliff_builder );
		$this->bundle_members                  = new TermBundleMembers( $wpdb );
		$this->user_id                         = null === $userId ? (int) User::getCurrentId() : $userId;
		$this->created_by_translate_everything = $createdByTranslateEverything;
		$this->sent_from_retry                 = $sentFromRetry;
	}

	public function prepareTermJob(
		int $termTaxonomyId,
		string $taxonomy,
		string $sourceLang,
		string $targetLang,
		bool $overwrite,
		bool $freshGeneration = false,
		?int $translatorId = null
	) {
		$prepared = $this->prepareTermJobWithRecord(
			$termTaxonomyId,
			$taxonomy,
			$sourceLang,
			$targetLang,
			$overwrite,
			$freshGeneration,
			$translatorId
		);

		return $prepared ? $prepared->getModel() : null;
	}

	public function prepareTermJobWithRecord(
		int $termTaxonomyId,
		string $taxonomy,
		string $sourceLang,
		string $targetLang,
		bool $overwrite,
		bool $freshGeneration = false,
		?int $translatorId = null
	) {
		$rows = $this->prepareTermRows( $termTaxonomyId, $taxonomy, $sourceLang, $targetLang, $overwrite, $translatorId );
		if ( null === $rows ) {
			return null;
		}
		$term                     = $rows['term'];
		$trid                     = $rows['trid'];
		$elementType              = $rows['elementType'];
		$targetTranslationId      = $rows['translationId'];
		$targetTranslationCreated = $rows['created'];
		$rid                      = $rows['rid'];
		$statusBefore             = $rows['statusBefore'];

		$insertedJobId = 0;
		$jobId         = $this->ensureTranslateJobRow( $rid, $term->name, $insertedJobId, $freshGeneration, $translatorId );
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
			$xliff = $this->xliff_builder->build( $term, $sourceLang, $targetLang, [ 'for_translation_proxy' => 0 === $translatorId ] );
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
					$statusBefore,
					$translatorId ?? 0
				),
			] );
			return null;
		}

		$ownedUnits = $this->ownedUnitsOf( $termTaxonomyId );

		$billing                 = $this->billingOf( $rows, null, $sourceLang, $targetLang );
		$model                   = $this->buildTaxonomyJobModel( $jobId, $rid, $termTaxonomyId, $term, $sourceLang, $targetLang, $xliff, $billing );
		$model->automatic        = null === $translatorId;
		$statusBefore['billing'] = [ 'words' => (int) ( $billing['words'] ?? $model->wpml_words_to_translate_count ), 'inFull' => $billing['inFull'] ];
		$this->prepared_snapshots[ $jobId ] = [
			'term'           => $term,
			'taxonomy'       => $taxonomy,
			'translationId'  => $targetTranslationId,
			'targetLanguage' => $targetLang,
			'units'          => self::requiredSentUnitIds( TaxonomyTermXliffReader::sentUnitIds( $xliff ) ),
			'owned_units'    => $ownedUnits,
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
			$statusBefore,
			$translatorId ?? 0
		);
	}

	private function prepareTermRows( int $termTaxonomyId, string $taxonomy, string $sourceLang, string $targetLang, bool $overwrite, ?int $translatorId ) {
		$wpdb                        = $this->wpdb;
		$this->last_refusal          = null;
		$this->last_carrying_post_id = null;

		$carried = CarriedTermPairs::one( $wpdb, $termTaxonomyId, $targetLang );
		if ( CarriedTermPairs::failed() ) {
			return null;
		}
		if ( null !== $carried ) {
			$this->last_refusal          = self::REFUSED_CARRIED_BY_POST;
			$this->last_carrying_post_id = $carried['postId'] ?: null;

			return null;
		}

		if ( null === $translatorId && AutomaticPairSupport::isRefused( (string) $sourceLang, (string) $targetLang ) ) {
			$this->last_refusal = self::REFUSED_UNSUPPORTED_PAIR;
			JobLog::add(
				'automatic_pair_unsupported_dropped',
				[
					'element_type' => 'tax_' . $taxonomy,
					'element_id'   => $termTaxonomyId,
					'source_lang'  => $sourceLang,
					'target_langs' => [ $targetLang ],
				]
			);

			return null;
		}

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
		$rid          = $this->ensureTranslationStatusRow( $targetTranslationId, $overwrite, $statusBefore, $term, $translatorId );
		if ( ! $rid ) {
			return null;
		}
		if ( $this->reconcileUncertainRid( $rid ) ) {
			return null;
		}

		$inFull = $statusBefore['translated'] && $statusBefore['upToDate'] && $overwrite && ! $this->created_by_translate_everything;
		$change = $statusBefore['translated'] && ! $inFull;
		unset( $statusBefore['translated'], $statusBefore['upToDate'] );

		return [
			'term'          => $term,
			'trid'          => $trid,
			'elementType'   => $elementType,
			'translationId' => $targetTranslationId,
			'created'       => $targetTranslationCreated,
			'rid'           => $rid,
			'statusBefore'  => $statusBefore,
			'change'        => $change,
			'inFull'        => $inFull && null === $translatorId,
		];
	}

	private function billingOf( array $rows, ?int $fullCount, string $sourceLang, string $targetLang ): array {
		$term = $rows['term'];
		if ( ! $rows['change'] ) {
			return ( null === $fullCount ? [] : [ 'words' => $fullCount ] ) + [ 'inFull' => (bool) $rows['inFull'] ];
		}

		return [
			'words'  => TermWords::countChanged(
				(int) $term->term_taxonomy_id,
				(string) $term->taxonomy,
				TermWords::fields( $term ),
				TermWords::textOf( (string) $term->name, (string) $term->description, TranslatableTermMeta::values( $term ) ),
				TermWords::baseline( (int) $term->term_id, $targetLang ),
				$sourceLang
			),
			'inFull' => false,
		];
	}

	public function prepareTermBundleMember( int $termTaxonomyId, string $taxonomy, string $sourceLang, string $targetLang, bool $overwrite ) {
		$this->last_refusal          = null;
		$this->last_carrying_post_id = null;

		$held = $this->bundle_members->liveBundleJobOf( $termTaxonomyId, $targetLang );
		if ( null !== $held ) {
			if ( $held['editor_job_id'] > 0 ) {
				$this->last_refusal = self::REFUSED_BEING_TRANSLATED;

				return null;
			}
			if ( $this->reconcileUncertainRid( $held['rid'] ) ) {
				return null;
			}
			$this->bundle_members->setState( $held['batch_job_id'], [ $held['term_rid'] ], TermBundleMembers::STATE_DROPPED );
		}

		$rows = $this->prepareTermRows( $termTaxonomyId, $taxonomy, $sourceLang, $targetLang, $overwrite, null );
		if ( null === $rows ) {
			return null;
		}
		$term = $rows['term'];

		if ( ! $rows['created'] ) {
			$this->writeTargetTranslationRow( $rows['trid'], $rows['elementType'], $targetLang, $sourceLang );
		}

		$member = new PreparedTaxonomyTermJob(
			null,
			$termTaxonomyId,
			$taxonomy,
			$sourceLang,
			$targetLang,
			$rows['translationId'],
			$rows['rid'],
			0,
			0,
			$rows['statusBefore'],
			0
		);

		if ( ! $this->snapshotMember( $member, $term ) ) {
			$this->releasePreparedJobs( [ $member ] );

			return null;
		}

		$words                   = (int) $this->prepared_members[ $rows['rid'] ]['words'];
		$billing                 = $this->billingOf( $rows, $words, $sourceLang, $targetLang );
		$statusBefore            = $rows['statusBefore'];
		$statusBefore['billing'] = [ 'words' => $billing['words'] ?? $words, 'inFull' => $billing['inFull'] ];

		return new PreparedTaxonomyTermJob(
			null,
			$termTaxonomyId,
			$taxonomy,
			$sourceLang,
			$targetLang,
			$rows['translationId'],
			$rows['rid'],
			0,
			0,
			$statusBefore,
			0
		);
	}

	public function adoptTermBundleMember( PreparedTaxonomyTermJob $member ): bool {
		if ( isset( $this->prepared_members[ $member->getRid() ] ) ) {
			return true;
		}
		$source = $this->resolveTaxonomyTermSource( $this->wpdb, $member->getTermTaxonomyId(), $member->getTaxonomy() );
		if ( null === $source ) {
			return false;
		}

		return $this->snapshotMember( $member, $source[0] );
	}

	public function holdsPreparedMember( int $rid ): bool {
		return isset( $this->prepared_members[ $rid ] );
	}

	private function snapshotMember( PreparedTaxonomyTermJob $member, \WP_Term $term ): bool {
		try {
			$units = $this->xliff_builder->unitsFor( $term, [ 'for_translation_proxy' => false ] );
		} catch ( \Throwable $e ) {
			return false;
		}

		$this->prepared_members[ $member->getRid() ] = [
			'term'           => $term,
			'taxonomy'       => $member->getTaxonomy(),
			'translationId'  => $member->getTranslationId(),
			'targetLanguage' => $member->getTargetLanguage(),
			'units'          => self::requiredSentUnitIds( array_column( $units, 'id' ) ),
			'owned_units'    => $this->ownedUnitsOf( $member->getTermTaxonomyId() ),
			'words'          => $this->countTaxonomyTermWords( $term, $member->getSourceLanguage() ),
			'depth'          => $this->taxonomyTermDepth( $term ),
		];

		return true;
	}

	public function buildTermBundles( array $members, bool $fullOnly = false ): array {
		$byPair = [];
		foreach ( $members as $member ) {
			if ( $member instanceof PreparedTaxonomyTermJob && isset( $this->prepared_members[ $member->getRid() ] ) ) {
				$kind = $this->memberBilling( $member )['inFull'] ? 'full' : 'change';

				$byPair[ $member->getSourceLanguage() . '|' . $member->getTargetLanguage() . '|' . $kind ][] = $member;
			}
		}

		$bundles = [];
		foreach ( $byPair as $pairMembers ) {
			usort(
				$pairMembers,
				function ( PreparedTaxonomyTermJob $a, PreparedTaxonomyTermJob $b ) {
					return [ $this->prepared_members[ $a->getRid() ]['depth'], $a->getTermTaxonomyId() ]
						<=> [ $this->prepared_members[ $b->getRid() ]['depth'], $b->getTermTaxonomyId() ];
				}
			);
			$sized = array_map(
				function ( PreparedTaxonomyTermJob $member ) {
					return [
						'term_taxonomy_id' => $member->getTermTaxonomyId(),
						'words'            => $this->prepared_members[ $member->getRid() ]['words'],
						'member'           => $member,
					];
				},
				$pairMembers
			);
			$chunks = TermBundle::split( $sized );
			if ( $fullOnly && $chunks ) {
				$last = $chunks[ count( $chunks ) - 1 ];
				if ( count( $last ) < TermBundle::MAX_TERMS && array_sum( array_column( $last, 'words' ) ) < TermBundle::MAX_WORDS ) {
					array_pop( $chunks );
				}
			}
			foreach ( $chunks as $chunk ) {
				$bundle = $this->buildTermBundle( array_column( $chunk, 'member' ) );
				if ( $bundle ) {
					$bundles[] = $bundle;
				}
			}
		}

		return $bundles;
	}

	private function buildTermBundle( array $members ) {
		global $sitepress;

		$sourceLang = $members[0]->getSourceLanguage();
		$targetLang = $members[0]->getTargetLanguage();
		$title      = $this->bundleTitle( $members );

		$batchId       = 0;
		$translationId = 0;
		$rid           = 0;
		$jobId         = 0;
		$insertedJobId = 0;
		$built         = false;
		try {
			$batchId = $this->createBundleBatch( $sourceLang, $targetLang );
			if ( $batchId <= 0 ) {
				return $this->bundleNotBuilt( 'batch', $members );
			}
			$wpdb                = $this->wpdb;
			$sourceTranslationId = (int) $sitepress->set_element_language_details( $batchId, TermBundle::ELEMENT_TYPE, null, $sourceLang );
			$trid                = $sourceTranslationId > 0 ? (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT trid FROM {$wpdb->prefix}icl_translations WHERE translation_id = %d", $sourceTranslationId )
			) : 0;
			$translationId       = $trid > 0 ? (int) $sitepress->set_element_language_details( null, TermBundle::ELEMENT_TYPE, $trid, $targetLang, $sourceLang ) : 0;
			if ( $translationId <= 0 ) {
				return $this->bundleNotBuilt( 'translation_row', $members );
			}
			$rid = $this->insertBundleStatusRow( $translationId, $batchId );
			if ( ! $rid ) {
				return $this->bundleNotBuilt( 'status_row', $members );
			}
			$jobId = $this->ensureTranslateJobRow( $rid, $title, $insertedJobId );
			if ( ! $jobId ) {
				return $this->bundleNotBuilt( 'job_row', $members );
			}

			$rows         = [];
			$snapshots    = [];
			$modelMembers = [];
			$terms        = [];
			$factory      = new \WPML\TM\Taxonomy\Job\TermJobRowFactory( $this->wpdb, $this->user_id );
			foreach ( $members as $member ) {
				$prepared = $this->prepared_members[ $member->getRid() ];
				$billing  = $this->memberBilling( $member );
				$billed = $this->created_by_translate_everything
					? $factory->translateEverythingWords( $member->getRid(), $jobId, (int) $prepared['words'], $billing['words'] )
					: $billing['words'];
				$row    = [
					'term_taxonomy_id' => $member->getTermTaxonomyId(),
					'rid'              => $member->getRid(),
					'words'            => $prepared['words'],
					'billed_words'     => $billed,
					'billed_in_full'   => $billing['inFull'],
				];
				$rows[]         = $row;
				$snapshots[]    = $prepared;
				$modelMembers[] = [ 'term' => $prepared['term'] ] + $row;
				$terms[]        = $prepared['term'];
			}
			if ( ! $this->bundle_members->add( $jobId, $targetLang, $rows ) ) {
				return $this->bundleNotBuilt( 'members', $members );
			}

			$xliff = $this->bundle_xliff_builder->build( $jobId, $terms, $sourceLang, $targetLang, [ 'for_translation_proxy' => false ] );
			$model = $this->buildTermBundleJobModel( $jobId, $rid, $batchId, $modelMembers, $sourceLang, $targetLang, $xliff, $title );

			foreach ( $members as $member ) {
				unset( $this->prepared_members[ $member->getRid() ] );
			}
			$this->prepared_snapshots[ $jobId ] = [ 'members' => $snapshots ];
			$built                              = true;

			return new PreparedTermBundle(
				new PreparedTaxonomyTermJob( $model, $batchId, TermBundle::ELEMENT_TYPE, $sourceLang, $targetLang, $translationId, $rid, $jobId, $insertedJobId, [ 'created' => true ], 0 ),
				$members
			);
		} catch ( \Throwable $e ) {
			return $this->bundleNotBuilt( 'exception', $members, $e );
		} finally {
			if ( ! $built ) {
				$this->releasePreparedBundles( [
					new PreparedTermBundle(
						new PreparedTaxonomyTermJob( null, $batchId, TermBundle::ELEMENT_TYPE, $sourceLang, $targetLang, $translationId, $rid, $jobId, $insertedJobId, [ 'created' => true ], 0 ),
						$members
					),
				] );
			}
		}
	}

	private function memberBilling( PreparedTaxonomyTermJob $member ): array {
		$billing = $member->getStatusBefore()['billing'] ?? null;
		if ( ! is_array( $billing ) ) {
			return [ 'words' => (int) ( $this->prepared_members[ $member->getRid() ]['words'] ?? 0 ), 'inFull' => false ];
		}

		return [ 'words' => (int) $billing['words'], 'inFull' => ! empty( $billing['inFull'] ) ];
	}

	private function bundleNotBuilt( $step, array $members, ?\Throwable $e = null ) {
		JobLog::addError(
			'term_bundle_not_built',
			[
				'step'              => $step,
				'source_lang'       => $members[0]->getSourceLanguage(),
				'target_lang'       => $members[0]->getTargetLanguage(),
				'term_taxonomy_ids' => array_map( function ( PreparedTaxonomyTermJob $member ) {
					return $member->getTermTaxonomyId();
				}, $members ),
				'db_error'          => (string) $this->wpdb->last_error,
				'exception'         => $e ? $e->getMessage() : null,
			]
		);

		return null;
	}

	public function releasePreparedBundles( array $bundles ): int {
		$restored = 0;
		foreach ( $bundles as $bundle ) {
			if ( ! $bundle instanceof PreparedTermBundle ) {
				continue;
			}
			$jobId = $bundle->getJobId();
			if ( $jobId > 0 && $this->remoteJobIdOfJobRow( $jobId ) > 0 ) {
				continue;
			}

			unset( $this->prepared_snapshots[ $jobId ] );
			$records = [];
			if ( $bundle->getRid() > 0 ) {
				$records[] = $bundle->getRecord();
			}
			$since = $jobId > 0 ? $jobId : PHP_INT_MAX;
			foreach ( $bundle->getMembers() as $member ) {
				unset( $this->prepared_members[ $member->getRid() ] );
				$records[] = new PreparedTaxonomyTermJob(
					null,
					$member->getTermTaxonomyId(),
					$member->getTaxonomy(),
					$member->getSourceLanguage(),
					$member->getTargetLanguage(),
					$member->getTranslationId(),
					$member->getRid(),
					$since,
					0,
					$member->getStatusBefore(),
					0
				);
			}
			$restored += ( new TaxonomyTermJobCleanup( $this->wpdb ) )->release( $records );
			if ( $jobId > 0 ) {
				$this->bundle_members->remove( $jobId );
			}
		}

		return $restored;
	}

	protected function createBundleBatch( string $sourceLang, string $targetLang ): int {
		return (int) \TranslationProxy_Batch::update_translation_batch(
			\TranslationProxy_Batch::get_generic_batch_name( true ) . ' ' . $sourceLang . '-' . $targetLang . ' ' . uniqid()
		);
	}

	private function bundleTitle( array $members ): string {
		$labels = [];
		foreach ( $members as $member ) {
			$taxonomy = $member->getTaxonomy();
			if ( ! isset( $labels[ $taxonomy ] ) ) {
				$object              = get_taxonomy( $taxonomy );
				$labels[ $taxonomy ] = $object && ! empty( $object->labels->name ) ? (string) $object->labels->name : $taxonomy;
			}
		}

		/* translators: 1: the taxonomies of the terms, comma separated; 2: how many terms the job holds */
		return sprintf( __( 'Terms: %1$s (%2$d)', 'sitepress' ), implode( ', ', $labels ), count( $members ) );
	}

	private function insertBundleStatusRow( int $translationId, int $batchId ): int {
		$wpdb = $this->wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'icl_translation_status',
			[
				'translation_id'      => $translationId,
				'status'              => ICL_TM_IN_PROGRESS,
				'translation_service' => 'local',
				'translator_id'       => 0,
				'batch_id'            => $batchId,
				'needs_update'        => 0,
				'md5'                 => '',
				'translation_package' => '',
			],
			[ '%d', '%d', '%s', '%d', '%d', '%d', '%s', '%s' ]
		);

		return (int) $wpdb->insert_id;
	}

	private function remoteJobIdOfJobRow( int $jobId ): int {
		$wpdb = $this->wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT editor_job_id FROM {$wpdb->prefix}icl_translate_job WHERE job_id = %d", $jobId )
		);
	}

	private function ownedUnitsOf( int $termTaxonomyId ): array {
		$ownedUnits = [];
		foreach ( (array) \WPML\TM\OwnedPackages\OwnerJobService::ownedStrings( 'term', $termTaxonomyId ) as $owned ) {
			list( $package, $string ) = $owned;
			$ownedUnits[] = [
				'field_type'     => \WPML_TM_Page_Builders_Field_Wrapper::generate_field_slug( $package->ID, $string->id ),
				'field_wrap_tag' => \WPML_TM_Page_Builders_Field_Wrapper::get_wrap_tag( $string ),
				'field_format'   => 'base64',
				'field_data'     => base64_encode( (string) $string->value ),
			];
		}

		return $ownedUnits;
	}

	public function releasePreparedJobs( array $preparedJobs ): int {
		foreach ( $preparedJobs as $preparedJob ) {
			if ( $preparedJob instanceof PreparedTaxonomyTermJob ) {
				unset( $this->prepared_snapshots[ $preparedJob->getJobId() ], $this->prepared_members[ $preparedJob->getRid() ] );
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

		$batches          = $this->ateBatches( $jobs );
		$acceptedMappings = [];
		$blockedMappings  = [];
		$rejectedJobIds   = [];
		$uncertainJobIds  = [];
		$failedJobIds     = [];

		foreach ( $batches as $index => $batch ) {
			$knownRemoteIds    = array_values( $acceptedMappings + $blockedMappings );
			$result            = $this->dispatchBatchToAte( $batch, $ateApi, $knownRemoteIds );
			$acceptedMappings += $result->getAcceptedMappings();
			$blockedMappings  += $result->getBlockedMappings();
			$rejectedJobIds    = array_merge( $rejectedJobIds, $result->getRejectedJobIds() );
			$uncertainJobIds   = array_merge( $uncertainJobIds, $result->getUncertainJobIds() );
			$failedJobIds      = array_merge( $failedJobIds, $result->getFailedJobIds() );

			if ( $result->hasFailure() ) {
				$remainingBatches = array_slice( $batches, $index + 1 );
				$remainingJobs    = $remainingBatches ? array_merge( ...$remainingBatches ) : [];
				$remainingJobIds = array_values( $this->requestedJobsByRid( $remainingJobs ) );

				return $this->parkForRetry(
					new TaxonomyTermDispatchResult(
						$acceptedMappings,
						$blockedMappings,
						$rejectedJobIds,
						$result->getErrorCode(),
						$result->getErrorMessage(),
						$remainingJobIds,
						$uncertainJobIds,
						$failedJobIds
					)
				);
			}
		}

		return $this->parkForRetry(
			new TaxonomyTermDispatchResult( $acceptedMappings, $blockedMappings, $rejectedJobIds, null, null, [], $uncertainJobIds, $failedJobIds )
		);
	}

	private function parksForRetry(): bool {
		return $this->created_by_translate_everything || $this->sent_from_retry;
	}

	private function parkForRetry( TaxonomyTermDispatchResult $result ): TaxonomyTermDispatchResult {
		$parked = $result->getParkedJobIds();
		if ( ! $parked || ! $this->parksForRetry() ) {
			return $result;
		}

		$this->parkJobs( $parked, $this->sent_from_retry );

		JobLog::add(
			'taxonomy_term_jobs_parked_for_retry',
			[
				'uncertain_job_ids' => $result->getUncertainJobIds(),
				'failed_job_ids'    => $result->getFailedJobIds(),
				'error_code'        => $result->getErrorCode(),
				'sent_from_retry'   => $this->sent_from_retry,
			]
		);

		return $result;
	}

	private function parkJobs( array $jobIds, bool $countRound ): void {
		$wpdb = $this->wpdb;
		foreach ( $jobIds as $jobId ) {
			if ( $countRound ) {
				$wpdb->query(
					$wpdb->prepare(
						"UPDATE {$wpdb->prefix}icl_translation_status
						 SET status = %d, ate_comm_retry_count = ate_comm_retry_count + 1
						 WHERE rid = ( SELECT rid FROM {$wpdb->prefix}icl_translate_job WHERE job_id = %d )",
						ICL_TM_ATE_NEEDS_RETRY,
						(int) $jobId
					)
				);
				continue;
			}
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->prefix}icl_translation_status
					 SET status = %d, ate_comm_retry_count = 0
					 WHERE rid = ( SELECT rid FROM {$wpdb->prefix}icl_translate_job WHERE job_id = %d )",
					ICL_TM_ATE_NEEDS_RETRY,
					(int) $jobId
				)
			);
		}
	}

	public function resendTermBundle( int $jobId ): string {
		$bundle = $this->parkedBundle( $jobId );
		if ( null === $bundle ) {
			$this->parkJobs( [ $jobId ], true );
			JobLog::addError( 'term_bundle_retry_unreadable', [ 'job_id' => $jobId ] );

			return self::RESEND_PARKED;
		}
		$rid = $bundle['rid'];

		if ( $bundle['editor_job_id'] > 0 ) {
			$this->setBundleStatus( $rid, ICL_TM_IN_PROGRESS );
			$this->clearDispatchUncertain( $jobId );
			JobLog::add( 'term_bundle_retry_already_bound', [ 'job_id' => $jobId, 'rid' => $rid, 'ate_job_id' => $bundle['editor_job_id'] ] );

			return self::RESEND_BOUND;
		}

		$members = $this->liveBundleMembers( $jobId, $bundle['source_lang'] );
		if ( ! $members ) {
			$this->retireParkedBundle( $jobId, $rid );
			JobLog::add( 'term_bundle_retry_retired_empty', [ 'job_id' => $jobId, 'rid' => $rid ] );

			return self::RESEND_RETIRED;
		}

		$claims      = $this->termPairClaims();
		$claimOwner  = 'retry-' . bin2hex( random_bytes( 16 ) );
		$pairs       = array_map(
			function ( array $member ) use ( $bundle ) {
				return [ $member['ttid'], $bundle['target_lang'] ];
			},
			$members
		);
		$claimedKeys = $claims->claimMany( $pairs, $claimOwner );
		$claimed     = array_values(
			array_filter(
				$pairs,
				function ( array $pair ) use ( $claimedKeys ) {
					return isset( $claimedKeys[ $pair[0] . '|' . $pair[1] ] );
				}
			)
		);
		if ( count( $claimed ) < count( $pairs ) ) {
			$claims->releaseMany( $claimed, $claimOwner );
			JobLog::add( 'term_bundle_retry_held_pair_claimed', [ 'job_id' => $jobId, 'rid' => $rid, 'claimed' => count( $claimed ), 'members' => count( $pairs ) ] );

			return self::RESEND_HELD;
		}

		try {
			$snapshots    = [];
			$modelMembers = [];
			$terms        = [];
			$unbilled     = false;
			foreach ( $members as $member ) {
				$snapshots[]    = $member['snapshot'];
				$modelMembers[] = [
					'term'           => $member['term'],
					'rid'            => $member['rid'],
					'words'          => $member['snapshot']['words'],
					'billed_words'   => $member['billed_words'],
					'billed_in_full' => $member['billed_in_full'],
				];
				$terms[]        = $member['term'];
				$unbilled       = $unbilled || null === $member['billed_words'];
			}
			$this->prepared_snapshots[ $jobId ] = [ 'members' => $snapshots ];

			if ( $this->reconcileUncertainRid( $rid ) ) {
				if ( self::REFUSED_BEING_TRANSLATED === $this->last_refusal ) {
					$this->storePreparedSourceSnapshot( $jobId );
					$this->setBundleStatus( $rid, ICL_TM_IN_PROGRESS );
					JobLog::add( 'term_bundle_retry_adopted_ate_job', [ 'job_id' => $jobId, 'rid' => $rid ] );

					return self::RESEND_ADOPTED;
				}

				$this->parkJobs( [ $jobId ], true );
				JobLog::add( 'term_bundle_retry_lookup_unanswered', [ 'job_id' => $jobId, 'rid' => $rid ] );

				return self::RESEND_PARKED;
			}

			$xliff = $this->bundle_xliff_builder->build( $jobId, $terms, $bundle['source_lang'], $bundle['target_lang'], [ 'for_translation_proxy' => false ] );
			$model = $this->buildTermBundleJobModel( $jobId, $rid, $bundle['batch_id'], $modelMembers, $bundle['source_lang'], $bundle['target_lang'], $xliff, $bundle['title'] );
			if ( $unbilled ) {
				$model->wpml_words_to_translate_count = $bundle['words'];
				$this->wpdb->update( $this->wpdb->prefix . 'icl_translate_job', [ 'wpml_words_to_translate_count' => $bundle['words'] ], [ 'job_id' => $jobId ], [ '%d' ], [ '%d' ] );
			}
			$result = $this->dispatchToAte( [ $model ] );

			if ( in_array( $jobId, $result->getAcceptedJobIds(), true ) || in_array( $jobId, $result->getBlockedJobIds(), true ) ) {
				$this->setBundleStatus( $rid, ICL_TM_IN_PROGRESS );
				JobLog::add(
					'term_bundle_retry_resent',
					[ 'job_id' => $jobId, 'rid' => $rid, 'blocked' => in_array( $jobId, $result->getBlockedJobIds(), true ), 'members' => count( $members ) ]
				);

				return self::RESEND_BOUND;
			}

			if ( ! in_array( $jobId, $result->getParkedJobIds(), true ) ) {
				$this->parkJobs( [ $jobId ], true );
			}
			JobLog::add( 'term_bundle_retry_failed_round', [ 'job_id' => $jobId, 'rid' => $rid, 'error_code' => $result->getErrorCode() ] );

			return self::RESEND_PARKED;
		} catch ( \Throwable $e ) {
			$this->parkJobs( [ $jobId ], true );
			JobLog::addError( 'term_bundle_retry_failed_round', [ 'job_id' => $jobId, 'rid' => $rid, 'exception' => $e->getMessage() ] );

			return self::RESEND_PARKED;
		} finally {
			unset( $this->prepared_snapshots[ $jobId ] );
			$claims->releaseMany( $claimed, $claimOwner );
		}
	}

	private function parkedBundle( int $jobId ) {
		$wpdb = $this->wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT j.rid, j.editor_job_id, j.title, j.wpml_words_to_translate_count,
				        target.language_code AS target_lang, target.source_language_code AS source_lang,
				        source.element_id AS batch_id
				 FROM {$wpdb->prefix}icl_translate_job j
				 INNER JOIN {$wpdb->prefix}icl_translation_status s ON s.rid = j.rid
				 INNER JOIN {$wpdb->prefix}icl_translations target ON target.translation_id = s.translation_id
				 INNER JOIN {$wpdb->prefix}icl_translations source
				         ON source.trid = target.trid AND source.source_language_code IS NULL
				 WHERE j.job_id = %d AND target.element_type = %s",
				$jobId,
				TermBundle::ELEMENT_TYPE
			),
			ARRAY_A
		);
		if ( ! is_array( $row ) || empty( $row['rid'] ) || empty( $row['target_lang'] ) || empty( $row['source_lang'] ) || empty( $row['batch_id'] ) ) {
			return null;
		}

		return [
			'rid'           => (int) $row['rid'],
			'editor_job_id' => (int) $row['editor_job_id'],
			'title'         => (string) $row['title'],
			'words'         => (int) ( $row['wpml_words_to_translate_count'] ?? 0 ),
			'source_lang'   => (string) $row['source_lang'],
			'target_lang'   => (string) $row['target_lang'],
			'batch_id'      => (int) $row['batch_id'],
		];
	}

	private function liveBundleMembers( int $jobId, string $sourceLang ): array {
		$wpdb    = $this->wpdb;
		$members = [];
		$gone    = [];
		foreach ( $this->bundle_members->membersOf( $jobId ) as $member ) {
			if ( TermBundleMembers::STATE_IN_BUNDLE !== $member['state'] ) {
				continue;
			}
			$taxonomy = (string) $wpdb->get_var(
				$wpdb->prepare( "SELECT taxonomy FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id = %d", $member['term_taxonomy_id'] )
			);
			$source   = '' !== $taxonomy ? $this->resolveTaxonomyTermSource( $wpdb, $member['term_taxonomy_id'], $taxonomy ) : null;
			if ( null === $source ) {
				$gone[] = $member['term_rid'];
				continue;
			}
			$term = $source[0];
			try {
				$units = $this->xliff_builder->unitsFor( $term, [ 'for_translation_proxy' => false ] );
			} catch ( \Throwable $e ) {
				$gone[] = $member['term_rid'];
				continue;
			}
			$members[] = [
				'ttid'           => $member['term_taxonomy_id'],
				'rid'            => $member['term_rid'],
				'term'           => $term,
				'billed_words'   => $member['billed_words'],
				'billed_in_full' => $member['billed_in_full'],
				'snapshot'       => [
					'term'           => $term,
					'taxonomy'       => $taxonomy,
					'translationId'  => 0,
					'targetLanguage' => $member['target_lang'],
					'units'          => self::requiredSentUnitIds( array_column( $units, 'id' ) ),
					'owned_units'    => $this->ownedUnitsOf( $member['term_taxonomy_id'] ),
					'words'          => $this->countTaxonomyTermWords( $term, $sourceLang ),
					'depth'          => $this->taxonomyTermDepth( $term ),
				],
			];
		}
		if ( $gone ) {
			$this->bundle_members->setState( $jobId, $gone, TermBundleMembers::STATE_DROPPED );
		}
		usort(
			$members,
			function ( array $a, array $b ) {
				return [ $a['snapshot']['depth'], $a['ttid'] ] <=> [ $b['snapshot']['depth'], $b['ttid'] ];
			}
		);

		return $members;
	}

	private function retireParkedBundle( int $jobId, int $rid ): void {
		PreviousStateServiceFactory::create()->revertInFlight( [ $rid ], [ ICL_TM_ATE_NEEDS_RETRY ] );
		$this->clearDispatchUncertain( $jobId );
	}

	protected function termPairClaims() {
		return new TaxonomyTermPairClaimRepository( $this->wpdb );
	}

	private function setBundleStatus( int $rid, int $status ): void {
		$this->wpdb->update(
			$this->wpdb->prefix . 'icl_translation_status',
			[ 'status' => $status ],
			[ 'rid' => $rid ],
			[ '%d' ],
			[ '%d' ]
		);
	}

	public function dispatchToTp( array $jobs, array $batchInfo, ?callable $recordBatch = null ): TaxonomyTermDispatchResult {
		$requestedIds = array_values( $this->requestedJobsByRid( $jobs ) );
		$project      = $this->tpProject();
		if ( ! $project ) {
			return new TaxonomyTermDispatchResult( [], [], $requestedIds, 'tp_project_unavailable' );
		}

		$accepted = [];
		foreach ( $jobs as $index => $job ) {
			$jobId      = (int) $job->id;
			$remaining  = array_values( $this->requestedJobsByRid( array_slice( $jobs, $index + 1 ) ) );
			$prepared   = $this->prepared_snapshots[ $jobId ] ?? null;
			$xliff      = isset( $job->file->content ) ? base64_decode( (string) $job->file->content, true ) : false;
			if ( ! $prepared || isset( $prepared['members'] ) || false === $xliff ) {
				return new TaxonomyTermDispatchResult( $accepted, [], array_merge( [ $jobId ], $remaining ), 'tp_prepared_term_missing' );
			}
			$cmsId    = \WPML_TM_CMS_ID::build_taxonomy_cms_id( $job->element_id, $prepared['taxonomy'], $job->source_language->code, $job->target_language->code );
			$identity = [ 'route' => 'tp', 'cms_id' => $cmsId, 'service_id' => (int) $project->service()->id ];
			if ( ! $this->markDispatchUncertain( [ $jobId ], $identity ) ) {
				return new TaxonomyTermDispatchResult( $accepted, [], array_merge( [ $jobId ], $remaining ), 'tp_identity_not_persisted' );
			}

			$term = $prepared['term'];
			try {
				$tpJobId = $project->send_to_translation_batch_mode(
					$xliff,
					'' !== (string) $term->name ? (string) $term->name : 'term-' . $term->term_id,
					$cmsId,
					$this->xliff_builder->termLink( $term ),
					$job->source_language->code,
					$job->target_language->code,
					(int) $job->wpml_words_to_translate_count,
					0,
					$this->xliff_builder->parentNote( $term ),
					null,
					$batchInfo
				);
			} catch ( \Throwable $e ) {
				return new TaxonomyTermDispatchResult( $accepted, [], [], 'tp_create_uncertain', $e->getMessage(), $remaining, [ $jobId ] );
			}

			if ( ! $tpJobId ) {
				$projectErrors = isset( $project->errors ) && is_array( $project->errors ) ? $project->errors : [];
				$projectError  = array_pop( $projectErrors );
				if ( $projectError instanceof \WP_Error ) {
					$errorCode = $projectError->get_error_code();
					$notSent   = in_array( $errorCode, [ 'wpml_tp_credentials_lost', 'wpml_tp_batch_unavailable' ], true );
					if ( $notSent ) {
						$this->clearDispatchUncertain( $jobId );
					}

					return new TaxonomyTermDispatchResult(
						$accepted,
						[],
						$notSent ? array_merge( [ $jobId ], $remaining ) : [],
						$errorCode,
						$projectError->get_error_message(),
						$notSent ? [] : $remaining,
						$notSent ? [] : [ $jobId ]
					);
				}

				return new TaxonomyTermDispatchResult( $accepted, [], [], 'tp_create_or_batch_uncertain', null, $remaining, [ $jobId ] );
			}
			if ( ! $this->updateTpStatus( (int) $job->source_id, (int) $prepared['translationId'], (int) $tpJobId, $identity['service_id'] ) ) {
				return new TaxonomyTermDispatchResult( $accepted, [], [], 'tp_status_persistence_failed', null, $remaining, [ $jobId ] );
			}

			$batchId = (int) $project->get_batch_job_id();
			if ( ! $batchId || ( $recordBatch && ! call_user_func( $recordBatch, (string) $batchInfo['batchName'], $batchId ) ) ) {
				return new TaxonomyTermDispatchResult( $accepted, [], [], 'tp_create_or_batch_uncertain', null, $remaining, [ $jobId ] );
			}

			$this->clearDispatchUncertain( $jobId );
			$this->storePreparedSourceSnapshot( $jobId );
			$accepted[ $jobId ] = (int) $tpJobId;
		}

		return new TaxonomyTermDispatchResult( $accepted, [], [] );
	}

	protected function tpProject() {
		return \TranslationProxy::get_current_project();
	}

	protected function updateTpStatus( int $rid, int $translationId, int $tpJobId, int $serviceId ): bool {
		$result = wpml_load_core_tm()->update_translation_status(
			[ 'translation_id' => $translationId, 'translation_service' => $serviceId, 'tp_id' => $tpJobId ],
			$rid
		);
		if ( ! is_array( $result ) || $rid !== (int) $result[0] ) {
			return false;
		}

		$wpdb = $this->wpdb;
		$storedTpJobId = $wpdb->get_var(
			$wpdb->prepare( "SELECT tp_id FROM {$wpdb->prefix}icl_translation_status WHERE rid = %d", $rid )
		);

		return empty( $wpdb->last_error ) && (int) $storedTpJobId === $tpJobId;
	}

	private function ateBatches( array $jobs ): array {
		$batches = [];
		foreach ( array_chunk( $jobs, self::ATE_BATCH_SIZE ) as $chunk ) {
			foreach ( [ true, false ] as $automatic ) {
				$routed = array_values(
					array_filter(
						$chunk,
						function ( $job ) use ( $automatic ): bool {
							return $automatic === (bool) ( $job->automatic ?? true );
						}
					)
				);
				if ( $routed ) {
					$batches[] = $routed;
				}
			}
		}

		return $batches;
	}

	private function dispatchBatchToAte( array $jobs, WPML_TM_ATE_API $ateApi, array $knownRemoteIds = [] ): TaxonomyTermDispatchResult {
		$requestedByRid = $this->requestedJobsByRid( $jobs );
		$requestedIds   = array_values( $requestedByRid );
		$automatic      = (bool) ( $jobs[0]->automatic ?? true );
		$payloadJobs    = array_map(
			function ( $job ) {
				$copy = clone $job;
				unset( $copy->automatic );

				return $copy;
			},
			$jobs
		);

		$encodedPayload = wp_json_encode(
			[
				'jobs'           => $payloadJobs,
				'existing_jobs'  => [],
				'auto_translate' => $automatic,
				'preview'        => $automatic && (bool) Option::shouldBeReviewed(),
				'job_type'       => $automatic ? 'auto' : \WPML\TM\API\Jobs::SENT_MANUALLY,
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
			if ( $this->parksForRetry() && in_array( (string) $response->get_error_code(), self::REFUSED_BEFORE_REQUEST_CODES, true ) ) {
				foreach ( $requestedIds as $requestedId ) {
					$this->clearDispatchUncertain( (int) $requestedId );
				}

				return new TaxonomyTermDispatchResult(
					[],
					[],
					[],
					self::ERROR_REFUSED_BEFORE_REQUEST,
					$response->get_error_message(),
					[],
					[],
					$requestedIds
				);
			}

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

			if ( $persistedMappings ) {
				do_action( 'wpml_tm_ate_jobs_created', $persistedMappings );
			}

			return new TaxonomyTermDispatchResult( [], $persistedMappings, [], $responseCode, $message, [], $uncertainIds );
		}

		$acceptedIds = array_map( 'intval', array_keys( $persistedMappings ) );
		TermJob::flagAcceptedJobs( $acceptedIds );
		if ( $persistedMappings ) {
			do_action( 'wpml_tm_ate_jobs_created', $persistedMappings );
		}
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

	private function markDispatchUncertain( array $jobIds, $identity = null ): bool {
		foreach ( $jobIds as $jobId ) {
			update_option( self::UNCERTAIN_OPTION_PREFIX . (int) $jobId, $identity ?: time(), false );
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

	private static function requiredSentUnitIds( array $unitIds ): array {
		return array_values(
			array_filter(
				$unitIds,
				function ( $unitId ) {
					return 0 !== strpos( (string) $unitId, 'package-string-' );
				}
			)
		);
	}

	private function storePreparedSourceSnapshot( int $jobId ): void {
		if ( ! isset( $this->prepared_snapshots[ $jobId ] ) ) {
			return;
		}

		$prepared = $this->prepared_snapshots[ $jobId ];
		foreach ( $prepared['members'] ?? [ $prepared ] as $member ) {
			$this->storeSourceSnapshot( $member['term'], $member['targetLanguage'] );
			if ( function_exists( 'update_term_meta' ) ) {
				update_term_meta( (int) $member['term']->term_id, self::SENT_UNITS_META_PREFIX . $member['targetLanguage'], $member['units'] );
			}
			$ownedUnits = $member['owned_units'] ?? [];
			if ( isset( $prepared['members'] ) ) {
				foreach ( $ownedUnits as &$unit ) {
					$unit['field_type'] = TermBundle::unitId( $unit['field_type'], (int) $member['term']->term_taxonomy_id );
				}
				unset( $unit );
			}
			$this->storePreparedOwnedUnits( $jobId, $ownedUnits );
		}
		unset( $this->prepared_snapshots[ $jobId ] );
	}

	private function storePreparedOwnedUnits( int $jobId, array $units ): void {
		foreach ( $units as $unit ) {
			$this->wpdb->insert(
				$this->wpdb->prefix . 'icl_translate',
				array_merge(
					[ 'job_id' => $jobId, 'content_id' => 0 ],
					$unit,
					[ 'field_translate' => 1, 'field_data_translated' => '', 'field_finished' => 0 ]
				),
				[ '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%d' ]
			);
		}
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
		$this->last_target_element_id = null;

		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT translation_id, element_id FROM {$wpdb->prefix}icl_translations
				WHERE trid = %d AND language_code = %s",
				$trid,
				$targetLang
			),
			ARRAY_A
		);
		if ( $existing ) {
			$this->last_target_element_id = (int) ( $existing['element_id'] ?? 0 ) ?: null;

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
		TermWords::rememberSent( $term, $targetLang );
	}

	public function getLastRefusal() {
		return $this->last_refusal;
	}

	public function getLastCarryingPostId(): ?int {
		return $this->last_carrying_post_id;
	}

	private function ensureTranslationStatusRow( int $translationId, bool $overwrite, array &$before, ?\WP_Term $term = null, ?int $translatorId = null ): int {
		$wpdb = $this->wpdb;
		$before = [];
		$translatedElementId = $this->last_target_element_id;

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

			if ( ! $overwrite && (int) ICL_TM_IN_PROGRESS === $currentStatus && $this->ridHasCurrentRemoteJob( (int) $existing['rid'], (string) ( $existing['translation_service'] ?? 'local' ) ) ) {
				$this->last_refusal = self::REFUSED_BEING_TRANSLATED;

				return 0;
			}

			if ( $overwrite && (int) ICL_TM_IN_PROGRESS === $currentStatus ) {
				$this->bundle_members->dropRid( (int) $existing['rid'] );
			}

			$failedStatuses = [ ICL_TM_IN_PROGRESS, ICL_TM_WAITING_FOR_TRANSLATOR, ICL_TM_ATE_UNSOLVABLE, ICL_TM_ATE_NEEDS_RETRY ];
			$failed         = in_array( $currentStatus, $failedStatuses, true );
			$needsUpdate    = (int) ( $existing['needs_update'] ?? 0 );
			$previousState  = PreviousStateServiceFactory::create();
			$settled        = $failed ? $previousState->get( $translationId ) : null;
			$upToDate       = 0 === $needsUpdate && PreviousStateServiceFactory::completedTranslationDetector()->isTranslationCompleted(
				$failed ? ICL_TM_IN_PROGRESS : $currentStatus,
				(bool) $needsUpdate,
				$translationId,
				$translatedElementId
			);

			$before = [
				'created'      => false,
				'status'       => $currentStatus,
				'needs_update' => $needsUpdate,
				'service'      => (string) ( $existing['translation_service'] ?? '' ),
				'translator'   => (int) ( $existing['translator_id'] ?? 0 ),
				'translated'   => (int) ICL_TM_COMPLETE === $currentStatus
					|| ( $failed ? (int) ICL_TM_COMPLETE === (int) ( $settled['status'] ?? 0 ) : $upToDate ),
				'upToDate'     => $upToDate,
			];
			if ( ! $settled || in_array( (int) $settled['status'], $failedStatuses, true ) ) {
				$previousState->update(
					$translationId,
					[
						'status'              => $currentStatus,
						'needs_update'        => (bool) $before['needs_update'],
						'md5'                 => (string) ( $existing['md5'] ?? '' ),
						'translation_service' => $before['service'],
						'translator_id'       => $before['translator'],
					]
				);
			} elseif ( 1 === $needsUpdate && empty( $settled['needs_update'] ) ) {
				$previousState->update( $translationId, [ 'needs_update' => true ] + $settled );
			}
			$wpdb->update(
				$wpdb->prefix . 'icl_translation_status',
				[
					'status'              => ICL_TM_IN_PROGRESS,
					'translation_service' => 'local',
					'translator_id'       => $translatorId ?? 0,
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
				'translator_id'       => $translatorId ?? 0,
				'batch_id'            => 0,
				'needs_update'        => 0,
				'md5'                 => $term ? $this->xliff_builder->sourceChecksum( $term ) : '',
				'translation_package' => '',
			],
			[ '%d', '%d', '%s', '%d', '%d', '%d', '%s', '%s' ]
		);
		$before = [ 'created' => true, 'translated' => null !== $translatedElementId, 'upToDate' => null !== $translatedElementId ];

		return (int) $wpdb->insert_id;
	}

	private function ensureTranslateJobRow( int $rid, string $title, int &$insertedJobId, bool $freshGeneration = false, ?int $translatorId = null ): int {
		$factory       = ( new \WPML\TM\Taxonomy\Job\TermJobRowFactory( $this->wpdb, $this->user_id ) )
			->withProvenance( $this->created_by_translate_everything, \WPML\Setup\Option::getReviewMode() )
			->withTranslator( $translatorId );
		$jobId         = $factory->resolveJobRow( $rid, $title, ! $freshGeneration );
		$insertedJobId = $factory->getInsertedJobId();

		return $jobId;
	}

	private function uncertainCurrentJob( int $rid ) {
		$wpdb = $this->wpdb;
		$row  = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT tj.job_id, tj.editor_job_id, tj.translated, ts.translation_id, ts.tp_id
				 FROM {$wpdb->prefix}icl_translate_job tj
				 INNER JOIN {$wpdb->prefix}icl_translation_status ts ON ts.rid = tj.rid
				 WHERE tj.rid = %d AND tj.revision IS NULL
				 ORDER BY tj.job_id DESC LIMIT 1",
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
			$this->clearDispatchUncertain( $jobId );
			$this->last_refusal = self::REFUSED_BEING_TRANSLATED;

			return true;
		}

		$identity = get_option( self::UNCERTAIN_OPTION_PREFIX . $jobId, false );
		if ( is_array( $identity ) && 'tp' === ( $identity['route'] ?? null ) ) {
			return $this->reconcileUncertainTp( $rid, $jobId, (int) $row['translation_id'], $identity );
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

	private function reconcileUncertainTp( int $rid, int $jobId, int $translationId, array $identity ): bool {
		if ( (int) ( $identity['lookups'] ?? 0 ) >= self::TP_RECONCILIATION_ATTEMPTS ) {
			$this->clearDispatchUncertain( $jobId );

			return false;
		}

		$cmsId     = isset( $identity['cms_id'] ) && is_string( $identity['cms_id'] ) ? $identity['cms_id'] : '';
		$serviceId = (int) ( $identity['service_id'] ?? 0 );
		if ( '' === $cmsId || 0 !== strpos( $cmsId, 'tax~' ) || ! $serviceId ) {
			return $this->deferUncertainTpRetry( $jobId, $identity );
		}

		try {
			$remote = wpml_tm_get_tp_jobs_api()->get_jobs_per_cms_ids( [ $cmsId ], false );
		} catch ( \Throwable $e ) {
			return $this->deferUncertainTpRetry( $jobId, $identity );
		}
		if ( ! is_array( $remote ) ) {
			return $this->deferUncertainTpRetry( $jobId, $identity );
		}
		if ( ! $remote ) {
			$this->clearDispatchUncertain( $jobId );

			return false;
		}

		$remoteJobId = 0;
		foreach ( $remote as $job ) {
			$id = is_object( $job ) && $cmsId === ( $job->cms_id ?? null ) ? $this->canonicalPositiveInteger( $job->id ?? null ) : null;
			if ( null === $id ) {
				return $this->deferUncertainTpRetry( $jobId, $identity );
			}
			$remoteJobId = max( $remoteJobId, $id );
		}
		if ( ! $this->updateTpStatus( $rid, $translationId, $remoteJobId, $serviceId ) ) {
			return $this->deferUncertainTpRetry( $jobId, $identity );
		}

		$this->clearDispatchUncertain( $jobId );
		$this->last_refusal = self::REFUSED_BEING_TRANSLATED;

		return true;
	}

	private function deferUncertainTpRetry( int $jobId, array $identity ): bool {
		$identity['lookups'] = (int) ( $identity['lookups'] ?? 0 ) + 1;
		if ( $identity['lookups'] >= self::TP_RECONCILIATION_ATTEMPTS ) {
			$this->clearDispatchUncertain( $jobId );

			return false;
		}

		update_option( self::UNCERTAIN_OPTION_PREFIX . $jobId, $identity, false );
		$this->last_refusal = self::REFUSED_DISPATCH_UNCERTAIN;

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

	private function ridHasCurrentRemoteJob( int $rid, string $translationService ): bool {
		$wpdb = $this->wpdb;

		if ( 'local' === $translationService ) {
			$remoteJobId = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT editor_job_id FROM (
					   ( SELECT j.editor_job_id FROM {$wpdb->prefix}icl_translate_job j
					     WHERE j.rid = %d AND j.revision IS NULL AND j.editor_job_id > 0 LIMIT 1 )
					   UNION ALL
					   ( SELECT j.editor_job_id FROM {$wpdb->prefix}icl_term_batch_members m
					     INNER JOIN {$wpdb->prefix}icl_translate_job j ON j.job_id = m.batch_job_id
					     WHERE m.term_rid = %d AND m.state = %s AND j.revision IS NULL AND j.editor_job_id > 0 LIMIT 1 )
					 ) current_job LIMIT 1",
					$rid,
					$rid,
					TermBundleMembers::STATE_IN_BUNDLE
				)
			);
		} else {
			$remoteJobId = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT ts.tp_id FROM {$wpdb->prefix}icl_translation_status ts WHERE ts.rid = %d AND ts.tp_id > 0 LIMIT 1",
					$rid
				)
			);
		}

		return (int) $remoteJobId > 0;
	}
}
