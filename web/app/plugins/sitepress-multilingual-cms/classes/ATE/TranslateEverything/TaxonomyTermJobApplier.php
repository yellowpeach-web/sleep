<?php

namespace WPML\TM\ATE\TranslateEverything;

use WPML\TM\API\ATE\LanguageMappings;
use WPML\TM\ATE\JobError\Recorder;
use WPML\TM\ATE\Review\TermJob;
use WPML\TM\Jobs\JobLog;
use WPML\TM\Taxonomy\TranslatableTermMeta;
use WPML\TM\XLIFF\TaxonomyTermXliffReader;
use WPML\TM\XLIFF\TermBundleXliffReader;

class TaxonomyTermJobApplier {

	private $wpdb;

	private $sitepress;

	private $reader;

	private $bundleReader;

	private $members;

	private $recorder;

	private $languageMappingsReader;

	private $languageMappings = null;

	private static $lastDeliveryGivenUp = false;

	private static $lastFailureReason = '';

	private static $lastFailureIsFinal = false;

	private static $memberFailures = [];

	const TERM_NAME_MAX_LENGTH = TermWriteRefusal::TERM_NAME_MAX_LENGTH;

	const NAME_TOO_LONG_REASON = 'The translated term name is %1$d characters long, and WordPress stores at most %2$d characters in a term name (term %3$d, into %4$s). Translate Everything will not send this term again until its name changes. Shorten the name in the original language, or translate the term by hand with a shorter name.';

	const NAME_TAKEN_REASON = 'The translated term name is already used by another term in this language (term %1$d, into %2$s). Translate Everything will not send this term again until its name changes. Rename the term in the original language, or translate it by hand with a different name.';

	const NAME_TAKEN_BY_REASON = 'The translated term name is already used by another term in this language (term %3$d holds it; translating term %1$d into %2$s). Translate Everything will not send this term again until its name changes. Rename the term in the original language, or translate it by hand with a different name.';

	public function __construct( \wpdb $wpdb, \SitePress $sitepress, ?TaxonomyTermXliffReader $reader = null, ?Recorder $recorder = null, ?callable $languageMappingsReader = null, ?TermBundleXliffReader $bundleReader = null, ?TermBundleMembers $members = null ) {
		$this->wpdb                   = $wpdb;
		$this->sitepress              = $sitepress;
		$this->reader                 = $reader ?: new TaxonomyTermXliffReader();
		$this->recorder               = $recorder;
		$this->languageMappingsReader = $languageMappingsReader;
		$this->bundleReader           = $bundleReader ?: new TermBundleXliffReader();
		$this->members                = $members ?: new TermBundleMembers( $wpdb );
	}

	public function apply( $xliff, $expectedJobId = null, $ateJobId = null ) {
		self::clearLastFailureReason();

		if ( TermBundleXliffReader::isTermBatchXliff( $xliff ) ) {
			return $this->applyBundle( $xliff, $expectedJobId, $ateJobId );
		}

		$data = $this->reader->read( $xliff );
		if ( is_wp_error( $data ) ) {
			return $this->fail( $data->get_error_message() );
		}

		$sourceTermId = (int) $data['termId'];
		$disable      = '__return_true';
		add_filter( 'wpml_disable_term_adjust_id', $disable, 999 );
		try {
			$term = get_term( $sourceTermId );
		} finally {
			remove_filter( 'wpml_disable_term_adjust_id', $disable, 999 );
		}
		if ( ! $term instanceof \WP_Term || (int) $term->term_id !== $sourceTermId ) {
			return $this->fail( 'source term not found for id ' . $data['termId'] );
		}

		$taxonomy    = $term->taxonomy;
		$elementType = 'tax_' . $taxonomy;

		$trid = (int) $this->sitepress->get_element_trid( (int) $term->term_taxonomy_id, $elementType );
		if ( ! $trid ) {
			return $this->fail( 'no trid for source term_taxonomy_id ' . $term->term_taxonomy_id );
		}

		list( $targetLang, $jobId ) = $this->resolveTargetLanguage( (string) $data['targetLang'], $trid, $expectedJobId, $ateJobId );

		if ( null !== $expectedJobId && (int) $jobId !== (int) $expectedJobId ) {
			return $this->fail(
				sprintf(
					'the delivered term XLIFF resolves to job %d but the delivery was bound to job %d',
					(int) $jobId,
					(int) $expectedJobId
				)
			);
		}

		if ( ! $this->isAwaitingDelivery( $jobId ) ) {
			return \WPML_TM_ATE_Jobs::SKIPPED_NOT_AWAITING_DELIVERY;
		}

		return $this->applyTerm( $term, $data, $trid, $targetLang, $jobId, $ateJobId );
	}

	protected function applyTerm( \WP_Term $term, array $data, $trid, $targetLang, $jobId, $ateJobId, $memberRid = 0 ) {
		$taxonomy     = $term->taxonomy;
		$sourceTermId = (int) $term->term_id;
		$rowJobId = $memberRid ? 0 : $jobId;

		if ( ! $this->sitepress->is_translated_taxonomy( $taxonomy ) ) {
			$this->cancelRefusedDelivery( $trid, $targetLang );

			return $this->fail(
				"taxonomy '{$taxonomy}' is no longer translatable - delivery refused, job cancelled (wpmldev-8066)"
			);
		}

		$missingUnits = $this->missingUnits( $data, $term, $targetLang );

		$tooLong = $this->nameTooLongReason(
			isset( $data['fields']['term-name'] ) ? (string) $data['fields']['term-name'] : '',
			$taxonomy,
			$sourceTermId,
			$targetLang
		);
		if ( '' !== $tooLong ) {
			return $this->refuseName( $tooLong, $term, $targetLang, $trid, $rowJobId );
		}

		$description  = ! in_array( 'term-description', $missingUnits, true ) && isset( $data['fields']['term-description'] )
			? wp_kses_post( $data['fields']['term-description'] )
			: '';
		$isNewTerm    = ! $this->sitepress->term_translations()->element_id_in( (int) $term->term_taxonomy_id, $targetLang );
		$ksesPriority = has_filter( 'pre_term_description', 'wp_filter_kses' );
		remove_filter( 'pre_term_description', 'wp_filter_kses' );
		$termLookupsInLanguage = $this->scopeTermLookupsToLanguage( $taxonomy, $targetLang );
		add_filter( 'terms_clauses', $termLookupsInLanguage, 20, 3 );
		try {
			$action     = new \WPML_Update_Term_Action(
				$this->wpdb,
				$this->sitepress,
				[
					'term'        => $data['fields']['term-name'],
					'description' => $description,
					'slug'        => $this->resolveTargetSlug( $trid, $taxonomy, $targetLang, $data['fields']['term-name'], $term->slug, $term->name ),
					'lang_code'   => $targetLang,
					'trid'        => (string) $trid,
					'taxonomy'    => $taxonomy,
				]
			);
			$newTerm    = $action->execute();
			$writeError = $action->get_error();
			if ( empty( $newTerm['term_taxonomy_id'] ) && $this->clashesOutsideLanguage( $writeError, $taxonomy, $targetLang ) ) {
				$lastTermsInLanguage = $this->keepTermsOfLanguage( $taxonomy, $targetLang );
				add_filter( 'get_terms', $lastTermsInLanguage, PHP_INT_MAX, 2 );
				try {
					$newTerm    = $action->execute();
					$writeError = $action->get_error();
				} finally {
					remove_filter( 'get_terms', $lastTermsInLanguage, PHP_INT_MAX );
				}
			}
		} catch ( \Throwable $e ) {
			return $this->fail( 'term write threw: ' . $e->getMessage() );
		} finally {
			remove_filter( 'terms_clauses', $termLookupsInLanguage, 20 );
			if ( false !== $ksesPriority ) {
				add_filter( 'pre_term_description', 'wp_filter_kses', (int) $ksesPriority );
			}
		}

		if ( empty( $newTerm['term_taxonomy_id'] ) ) {
			return $this->failTermWrite( $writeError, $term, $targetLang, $trid, $rowJobId );
		}

		RefusedTermName::clear( $sourceTermId, $targetLang );

		$this->finalize( $trid, $targetLang, (int) $newTerm['term_taxonomy_id'], $jobId, ! $missingUnits, $memberRid, $sourceTermId );

		( new \WPML_Sync_Term_Meta_Action( $this->sitepress, (int) $newTerm['term_taxonomy_id'], $isNewTerm ) )->run();

		$this->applyTranslatedTermMeta( $newTerm, $data['fields'], $term, $targetLang );
		$missingOwnedUnits = $this->missingOwnedUnits( $data, $jobId, $term, $memberRid ? (int) $term->term_taxonomy_id : 0 );
		$this->applyTranslatedOwnedStrings( $data['fields'], $targetLang, $jobId, $missingOwnedUnits, (int) $newTerm['term_id'] );
		$this->flagOwnedUnitsNeedsUpdate( $missingOwnedUnits, $term, $jobId, $memberRid );

		$this->syncTermParent( $taxonomy );

		if ( $missingUnits ) {
			$reason = sprintf(
				'the delivery lacks the translation of %1$s, which the job sent, for term %2$d (%3$s) into %4$s: the units it carries were written and the job stays not complete',
				implode( ', ', $missingUnits ),
				$sourceTermId,
				$term->name,
				$targetLang
			);
			if ( $memberRid ) {
				return $this->fail( $reason );
			}
			$this->giveUpIncompleteDelivery( $jobId, $ateJobId, $reason );
		}

		return $jobId ?: true;
	}

	private function applyBundle( $xliff, $expectedJobId, $ateJobId ) {
		$bundle = $this->bundleReader->read( $xliff );
		if ( is_wp_error( $bundle ) ) {
			return $this->fail( $bundle->get_error_message() );
		}

		$fileJobId = (int) $bundle['bundleJobId'];
		$jobId     = $this->deliveredBundleJob( $fileJobId, $expectedJobId, $ateJobId );
		$fileRid = $jobId === $fileJobId ? 0 : $this->ridOfJob( $fileJobId );
		if ( $jobId !== $fileJobId && ( ! $fileRid || $this->ridOfJob( $jobId ) !== $fileRid ) ) {
			return $this->fail( sprintf( 'the delivered term bundle is job %d but the delivery was bound to job %d', $fileJobId, $jobId ) );
		}

		if ( ! $this->isAwaitingDelivery( $jobId ) ) {
			return \WPML_TM_ATE_Jobs::SKIPPED_NOT_AWAITING_DELIVERY;
		}

		$targetLang = $this->jobLanguageOfJob( $jobId ) ?: (string) $bundle['targetLang'];

		$members = [];
		foreach ( $this->members->membersOf( $jobId ) as $member ) {
			$members[ $member['term_taxonomy_id'] ] = $member;
		}
		$applied = [];

		foreach ( $bundle['terms'] as $ttid => $termData ) {
			$member = isset( $members[ $ttid ] ) ? $members[ $ttid ] : null;
			if ( ! $member || TermBundleMembers::STATE_IN_BUNDLE !== $member['state'] ) {
				continue;
			}
			unset( $members[ $ttid ] );
			$rid = (int) $member['term_rid'];

			if ( ! $this->memberAwaitsDelivery( $rid ) ) {
				$this->members->setState( $jobId, [ $rid ], TermBundleMembers::STATE_DROPPED );
				continue;
			}

			self::$lastFailureReason  = '';
			self::$lastFailureIsFinal = false;

			$term = $this->sourceTermByTaxonomyId( (int) $ttid );
			$trid = $term ? (int) $this->sitepress->get_element_trid( (int) $ttid, 'tax_' . $term->taxonomy ) : 0;
			if ( ! $term || ! $trid ) {
				$this->giveUpMember( $jobId, (int) $ttid, $rid, $term ? 'no trid for source term_taxonomy_id ' . $ttid : 'source term not found for term_taxonomy_id ' . $ttid );
				continue;
			}

			$data = [
				'termId'     => (int) $term->term_id,
				'targetLang' => $targetLang,
				'fields'     => $termData['fields'],
				'sentUnits'  => $termData['sentUnits'],
			];
			if ( false === $this->applyTerm( $term, $data, $trid, $targetLang, $jobId, $ateJobId, $rid ) ) {
				$this->giveUpMember( $jobId, (int) $ttid, $rid, self::$lastFailureReason );
				continue;
			}
			$this->members->setState( $jobId, [ $rid ], TermBundleMembers::STATE_APPLIED );
			$applied[] = $rid;
		}
		if ( $jobId !== $fileJobId && $applied ) {
			$this->members->setState( $fileJobId, $applied, TermBundleMembers::STATE_APPLIED );
		}

		foreach ( $members as $ttid => $member ) {
			if ( TermBundleMembers::STATE_IN_BUNDLE === $member['state'] ) {
				$this->giveUpMember( $jobId, (int) $ttid, (int) $member['term_rid'], sprintf( 'the delivery lacks term %d, which the bundle sent, into %s', (int) $ttid, $targetLang ) );
			}
		}

		self::$lastFailureReason  = '';
		self::$lastFailureIsFinal = false;

		$states = array_column( $this->members->membersOf( $jobId ), 'state' );
		if ( in_array( TermBundleMembers::STATE_IN_BUNDLE, $states, true ) ) {
			return $jobId;
		}
		if ( $jobId !== $fileJobId ) {
			( $this->recorder ?: new Recorder() )->clear( $fileJobId );
		}
		$counts  = array_count_values( $states );
		$givenUp = $counts[ TermBundleMembers::STATE_GIVEN_UP ] ?? 0;
		if ( $givenUp ) {
			$total = count( $states ) - ( $counts[ TermBundleMembers::STATE_DROPPED ] ?? 0 );
			$this->giveUpIncompleteDelivery(
				$jobId,
				$ateJobId,
				sprintf(
					/* translators: 1: how many terms of the bundle could not be translated, 2: how many terms the bundle holds, 3: the reasons, one per failed term, separated by "; ". */
					_n(
						'%1$d of %2$d term of the bundle could not be translated: %3$s',
						'%1$d of %2$d terms of the bundle could not be translated: %3$s',
						$total,
						'wpml'
					),
					$givenUp,
					$total,
					implode( '; ', array_column( self::$memberFailures, 'reason' ) )
				)
			);
			if ( ! empty( $counts[ TermBundleMembers::STATE_APPLIED ] ) ) {
				$this->markJobTranslated( $jobId );
			} else {
				TermJob::onNothingDelivered( $jobId );
			}

			return $jobId;
		}

		$this->completeBundle( $jobId );

		return $jobId;
	}

	private function deliveredBundleJob( $fileJobId, $expectedJobId, $ateJobId ) {
		if ( $expectedJobId ) {
			return (int) $expectedJobId;
		}
		if ( $ateJobId ) {
			$wpdb  = $this->wpdb;
			$byAte = (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT job_id FROM {$wpdb->prefix}icl_translate_job WHERE editor_job_id = %d ORDER BY job_id DESC LIMIT 1", (int) $ateJobId )
			);
			if ( $byAte ) {
				return $byAte;
			}
		}

		return (int) $fileJobId;
	}

	private function giveUpMember( $jobId, $termTaxonomyId, $rid, $reason ) {
		$wpdb = $this->wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}icl_translation_status SET status = %d WHERE rid = %d AND status IN ( %d, %d )",
				ICL_TM_ATE_UNSOLVABLE,
				(int) $rid,
				ICL_TM_WAITING_FOR_TRANSLATOR,
				ICL_TM_IN_PROGRESS
			)
		);
		$this->members->giveUp( $jobId, (int) $rid, (string) $reason );

		self::$memberFailures[] = [ 'term_taxonomy_id' => (int) $termTaxonomyId, 'rid' => (int) $rid, 'reason' => (string) $reason ];
		JobLog::add( 'term_bundle_member_given_up', [ 'job_id' => (int) $jobId, 'term_taxonomy_id' => (int) $termTaxonomyId, 'rid' => (int) $rid, 'reason' => (string) $reason ] );
	}

	private function completeBundle( $jobId ) {
		$rid = $this->ridOfJob( $jobId );
		if ( $rid ) {
			$this->wpdb->update(
				$this->wpdb->prefix . 'icl_translation_status',
				[ 'status' => ICL_TM_COMPLETE ],
				[ 'rid' => $rid ],
				[ '%d' ],
				[ '%d' ]
			);
		}
		$this->markJobTranslated( $jobId );
	}

	private function memberAwaitsDelivery( $rid ) {
		$wpdb   = $this->wpdb;
		$status = $wpdb->get_var(
			$wpdb->prepare( "SELECT status FROM {$wpdb->prefix}icl_translation_status WHERE rid = %d", (int) $rid )
		);

		return null === $status
			|| in_array( (int) $status, [ ICL_TM_WAITING_FOR_TRANSLATOR, ICL_TM_IN_PROGRESS, ICL_TM_TRANSLATION_READY_TO_DOWNLOAD ], true );
	}

	private function statusRowOf( $rid ) {
		$wpdb = $this->wpdb;
		$row  = $wpdb->get_row(
			$wpdb->prepare( "SELECT translation_id, rid FROM {$wpdb->prefix}icl_translation_status WHERE rid = %d", (int) $rid )
		);

		return $row ?: (object) [ 'translation_id' => 0, 'rid' => 0 ];
	}

	private function jobLanguageOfJob( $jobId ) {
		$wpdb     = $this->wpdb;
		$language = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT t.language_code
				FROM {$wpdb->prefix}icl_translate_job tj
				INNER JOIN {$wpdb->prefix}icl_translation_status ts ON ts.rid = tj.rid
				INNER JOIN {$wpdb->prefix}icl_translations t ON t.translation_id = ts.translation_id
				WHERE tj.job_id = %d",
				(int) $jobId
			)
		);

		return is_string( $language ) && '' !== $language ? $language : null;
	}

	private function sourceTermByTaxonomyId( $termTaxonomyId ) {
		$disable = '__return_true';
		add_filter( 'wpml_disable_term_adjust_id', $disable, 999 );
		try {
			$term = get_term_by( 'term_taxonomy_id', (int) $termTaxonomyId );
		} finally {
			remove_filter( 'wpml_disable_term_adjust_id', $disable, 999 );
		}

		return $term instanceof \WP_Term && (int) $term->term_taxonomy_id === (int) $termTaxonomyId ? $term : null;
	}

	private function giveUpIncompleteDelivery( $jobId, $ateJobId, $reason ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'WPML taxonomy term translation is incomplete, the job was given up: ' . $reason );
		}

		if ( ! $jobId ) {
			return;
		}

		if ( ! $ateJobId ) {
			$wpdb     = $this->wpdb;
			$ateJobId = (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT editor_job_id FROM {$wpdb->prefix}icl_translate_job WHERE job_id = %d", $jobId )
			);
		}

		$recorder = $this->recorder ?: new Recorder();
		$recorder->giveUp( (int) $jobId, (int) $ateJobId, Recorder::TYPE_APPLY, $reason );

		self::$lastDeliveryGivenUp = true;
	}

	private function nameTooLongReason( $translatedName, $taxonomy, $sourceTermId, $targetLang ) {
		return TermWriteRefusal::tooLongReason( self::NAME_TOO_LONG_REASON, $translatedName, $taxonomy, $sourceTermId, $targetLang, $this->wpdb );
	}

	private function failTermWrite( $error, \WP_Term $sourceTerm, $targetLang, $trid, $jobId ) {
		if (
			$error instanceof \WP_Error
			&& 'term_exists' === $error->get_error_code()
			&& ! $this->clashesOutsideLanguage( $error, $sourceTerm->taxonomy, (string) $targetLang )
		) {
			$holderId = $error->get_error_data();
			$holderId = is_numeric( $holderId ) ? (int) $holderId : 0;

			return $this->refuseName(
				$holderId > 0
					? sprintf( self::NAME_TAKEN_BY_REASON, (int) $sourceTerm->term_id, (string) $targetLang, $holderId )
					: sprintf( self::NAME_TAKEN_REASON, (int) $sourceTerm->term_id, (string) $targetLang ),
				$sourceTerm,
				$targetLang,
				$trid,
				$jobId
			);
		}

		$reason = $this->termWriteFailureReason( $error, (int) $sourceTerm->term_id, $sourceTerm->name, $targetLang );
		if ( $this->clashesOutsideLanguage( $error, $sourceTerm->taxonomy, (string) $targetLang ) ) {
			$reason .= sprintf(
				' (the term it names, %1$d, is in %2$s: no clash in %3$s)',
				(int) $error->get_error_data(),
				(string) $this->languageOfClashingTerm( $error, $sourceTerm->taxonomy ),
				$targetLang
			);
		}

		return $this->fail( $reason );
	}

	private function refuseName( $reason, \WP_Term $sourceTerm, $targetLang, $trid, $jobId ) {
		$sentName = $this->sentSourceName( $sourceTerm, (string) $targetLang, (int) $trid, (int) $jobId );
		if ( null !== $sentName ) {
			RefusedTermName::record( (int) $sourceTerm->term_id, $sentName, (string) $targetLang, $reason );
		}

		$this->fail( $reason );
		self::$lastFailureIsFinal = true;

		return false;
	}

	private function sentSourceName( \WP_Term $sourceTerm, $targetLang, $trid, $jobId ) {
		$prefix   = class_exists( \WPML\TM\Dashboard\Taxonomy\TaxonomyDashboardData::class )
			? \WPML\TM\Dashboard\Taxonomy\TaxonomyDashboardData::WC_SNAPSHOT_META_PREFIX
			: '_wpml_tax_wc_src_';
		$snapshot = function_exists( 'get_term_meta' )
			? get_term_meta( (int) $sourceTerm->term_id, $prefix . $targetLang, true )
			: '';

		if ( is_string( $snapshot ) && '' !== $snapshot ) {
			$lines = explode( "\n", $snapshot );

			return (string) $lines[0];
		}

		if ( $this->sourceChangedSinceSend( $trid, $targetLang, $jobId ) ) {
			return null;
		}

		return (string) $sourceTerm->name;
	}

	private function sourceChangedSinceSend( $trid, $targetLang, $jobId ) {
		$wpdb = $this->wpdb;

		if ( $jobId ) {
			$needsUpdate = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT ts.needs_update
					 FROM {$wpdb->prefix}icl_translate_job tj
					 INNER JOIN {$wpdb->prefix}icl_translation_status ts ON ts.rid = tj.rid
					 WHERE tj.job_id = %d",
					$jobId
				)
			);
		} else {
			$needsUpdate = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT ts.needs_update
					 FROM {$wpdb->prefix}icl_translations t
					 INNER JOIN {$wpdb->prefix}icl_translation_status ts ON ts.translation_id = t.translation_id
					 WHERE t.trid = %d AND t.language_code = %s",
					$trid,
					$targetLang
				)
			);
		}

		return 1 === (int) $needsUpdate;
	}

	private function missingUnits( array $data, \WP_Term $term, $targetLang ) {
		$recorded = function_exists( 'get_term_meta' )
			? get_term_meta( (int) $term->term_id, TaxonomyTermJobDispatcher::SENT_UNITS_META_PREFIX . $targetLang, true )
			: [];
		$sent     = array_merge(
			is_array( $recorded ) ? $recorded : [],
			isset( $data['sentUnits'] ) && is_array( $data['sentUnits'] ) ? $data['sentUnits'] : []
		);

		$missing = [];
		foreach ( array_unique( array_map( 'strval', $sent ) ) as $unitId ) {
			$target = isset( $data['fields'][ $unitId ] ) ? (string) $data['fields'][ $unitId ] : '';
			if ( 0 !== strpos( $unitId, 'package-string-' ) && '' !== $unitId && '' === trim( $target ) ) {
				$missing[] = $unitId;
			}
		}

		return $missing;
	}

	private function missingOwnedUnits( array $data, $jobId, \WP_Term $term, $memberTtid = 0 ) {
		$wpdb  = $this->wpdb;
		$units = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT field_type FROM {$wpdb->prefix}icl_translate WHERE job_id = %d",
				(int) $jobId
			)
		);
		if ( $memberTtid ) {
			$suffix = '_' . (int) $memberTtid;
			$units  = array_map(
				function ( $unit ) use ( $memberTtid ) {
					return (object) [ 'field_type' => TermBundle::singleTermUnitId( (string) $unit->field_type, $memberTtid ) ];
				},
				array_filter(
					(array) $units,
					function ( $unit ) use ( $suffix ) {
						return substr( (string) $unit->field_type, -strlen( $suffix ) ) === $suffix;
					}
				)
			);
		}
		$ownedStrings = \WPML\TM\OwnedPackages\OwnerJobService::ownedStrings( 'term', (int) $term->term_taxonomy_id, true );
		$live = [];
		$emptySources = [];
		foreach ( (array) $ownedStrings as $owned ) {
			$slug = \WPML_TM_Page_Builders_Field_Wrapper::generate_field_slug( $owned[0]->ID, $owned[1]->id );
			if ( '' === trim( (string) $owned[1]->value ) ) {
				$emptySources[ $slug ] = true;
			} else {
				$live[ $slug ] = true;
			}
		}

		$missing = [];
		foreach ( (array) $units as $unit ) {
			$unitId = (string) $unit->field_type;
			$target = isset( $data['fields'][ $unitId ] ) ? (string) $data['fields'][ $unitId ] : '';
			if ( 0 === strpos( $unitId, 'package-string-' ) && ! isset( $emptySources[ $unitId ] ) && ( '' === trim( $target ) || ( null !== $ownedStrings && ! isset( $live[ $unitId ] ) ) ) ) {
				$missing[] = $unitId;
			}
		}

		return $missing;
	}

	private function applyTranslatedTermMeta( array $newTerm, array $fields, \WP_Term $sourceTerm, $targetLang ) {
		if ( ! function_exists( 'update_term_meta' ) ) {
			return;
		}

		$termId = isset( $newTerm['term_id'] ) ? (int) $newTerm['term_id'] : 0;
		if ( ! $termId && ! empty( $newTerm['term_taxonomy_id'] ) ) {
			$termId = (int) $this->wpdb->get_var(
				$this->wpdb->prepare(
					"SELECT term_id FROM {$this->wpdb->term_taxonomy} WHERE term_taxonomy_id = %d",
					(int) $newTerm['term_taxonomy_id']
				)
			);
		}
		if ( ! $termId ) {
			return;
		}

		$prefix  = TranslatableTermMeta::UNIT_ID_PREFIX;
		$context = [
			'sourceTermId' => (int) $sourceTerm->term_id,
			'taxonomy'     => (string) $sourceTerm->taxonomy,
			'targetLang'   => (string) $targetLang,
		];

		$arrayValues = [];
		foreach ( $fields as $unitId => $value ) {
			if ( 0 !== strpos( (string) $unitId, $prefix ) ) {
				continue;
			}
			$key = substr( (string) $unitId, strlen( $prefix ) );
			if ( '' === $key || '' === (string) $value ) {
				continue;
			}

			$entry = $this->arrayEntryOfSourceMeta( $sourceTerm, $key );
			if ( $entry ) {
				list( $metaKey, $path ) = $entry;
				$arrayValues[ $metaKey ] = $this->withLeafReplaced(
					$arrayValues[ $metaKey ] ?? get_term_meta( (int) $sourceTerm->term_id, $metaKey, true ),
					$path,
					(string) $value
				);
				continue;
			}

			$this->writeTranslatedTermMeta( $termId, $key, (string) $value, $context );
		}

		foreach ( $arrayValues as $metaKey => $arrayValue ) {
			$this->writeTranslatedTermMeta( $termId, $metaKey, $arrayValue, $context );
		}
	}

	private function applyTranslatedOwnedStrings( array $fields, $targetLang, $jobId, array $lost = [], $targetTermId = 0 ) {
		$state            = $this->jobDeliveryState( $jobId );
		$appliedStringIds = [];
		foreach ( $fields as $unitId => $value ) {
			if ( 0 !== strpos( (string) $unitId, 'package-string-' ) || '' === (string) $value || in_array( (string) $unitId, $lost, true ) ) {
				continue;
			}

			$stringId            = (int) substr( (string) $unitId, strrpos( (string) $unitId, '-' ) + 1 );
			$appliedStringIds[]  = $stringId;
			do_action( 'wpml_add_string_translation', $stringId, $targetLang, (string) $value, 10, $state->translator_id, $state->translation_service );
		}

		if ( $appliedStringIds && $targetTermId ) {
			do_action( 'wpml_taxonomy_term_owned_strings_applied', (int) $targetTermId, $appliedStringIds );
		}
	}

	private function jobDeliveryState( $jobId ) {
		$wpdb = $this->wpdb;
		$state = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT job_status.translator_id, job_status.translation_service, job_status.translation_id, job_status.rid
				FROM {$wpdb->prefix}icl_translate_job job
				INNER JOIN {$wpdb->prefix}icl_translation_status job_status ON job_status.rid = job.rid
				WHERE job.job_id = %d",
				(int) $jobId
			)
		);

		return $state ?: (object) [ 'translator_id' => 0, 'translation_service' => 'local', 'translation_id' => 0, 'rid' => 0 ];
	}

	private function flagOwnedUnitsNeedsUpdate( array $missing, \WP_Term $term, $jobId, $memberRid = 0 ) {
		if ( ! $missing ) {
			return;
		}

		JobLog::runInGroup(
			JobLog::GROUP_ID_DOWNLOAD_JOBS,
			'Standalone term owned strings',
			function () use ( $missing, $term, $jobId, $memberRid ) {
				JobLog::add( 'owned_string_delivery_empty', [ 'term_id' => (int) $term->term_id, 'units' => $missing ] );
				$state = $memberRid ? $this->statusRowOf( $memberRid ) : $this->jobDeliveryState( $jobId );
				if ( ! empty( $state->translation_id ) && ! empty( $state->rid ) ) {
					wpml_load_core_tm()->update_translation_status(
						[ 'translation_id' => (int) $state->translation_id, 'needs_update' => 1 ],
						(int) $state->rid
					);
				}
			}
		);
	}

	private function writeTranslatedTermMeta( $termId, $metaKey, $value, array $context ) {
		$handled = apply_filters( 'wpml_apply_translated_term_meta', false, $termId, $metaKey, $value, $context );
		if ( true !== $handled ) {
			update_term_meta( $termId, $metaKey, $value );
		}
	}

	private function arrayEntryOfSourceMeta( \WP_Term $sourceTerm, $unitKey ) {
		$entry = TranslatableTermMeta::parseEntryKey( $unitKey );
		if ( ! $entry || metadata_exists( 'term', (int) $sourceTerm->term_id, $unitKey ) ) {
			return null;
		}
		list( $metaKey, $path ) = $entry;
		$sourceValue            = get_term_meta( (int) $sourceTerm->term_id, $metaKey, true );

		return is_array( $sourceValue ) && is_scalar( $this->leafAt( $sourceValue, $path ) ) ? $entry : null;
	}

	private function leafAt( array $value, array $path ) {
		foreach ( $path as $segment ) {
			if ( ! is_array( $value ) || ! array_key_exists( $segment, $value ) ) {
				return null;
			}
			$value = $value[ $segment ];
		}

		return $value;
	}

	private function withLeafReplaced( array $value, array $path, $leaf ) {
		$segment = array_shift( $path );
		$value[ $segment ] = $path ? $this->withLeafReplaced( (array) $value[ $segment ], $path, $leaf ) : $leaf;

		return $value;
	}

	private function syncTermParent( $taxonomy ) {
		if ( ! is_taxonomy_hierarchical( $taxonomy ) ) {
			return;
		}

		$sync = wpml_get_hierarchy_sync_helper( 'term' );
		if ( ! $sync ) {
			return;
		}

		\WPML_Non_Persistent_Cache::flush_group( \WPML_Hierarchy_Sync::CACHE_GROUP );

		try {
			if ( $sync->is_need_sync( $taxonomy ) ) {
				$sync->sync_element_hierarchy( $taxonomy );
			}
		} catch ( \Throwable $e ) {
			$this->fail( 'term parent sync threw: ' . $e->getMessage() );
		}
	}

	private function resolveTargetSlug( $trid, $taxonomy, $targetLang, $translatedName, $sourceSlug = '', $sourceName = '' ) {
		$currentSlug = $this->getTargetTermSlug( $trid, $taxonomy, $targetLang );

		if ( '' !== $currentSlug ) {
			if ( ! $this->slugTakenInLanguage( $currentSlug, $taxonomy, $targetLang, $trid ) ) {
				return '';
			}

			return $this->uniqueSlugInLanguage( sanitize_title( $translatedName ), $taxonomy, $targetLang, $trid );
		}

		$sameName = '' !== (string) $sourceSlug
			&& $this->plainName( $translatedName ) === $this->plainName( $sourceName );

		return $this->uniqueSlugInLanguage(
			$sameName ? (string) $sourceSlug : sanitize_title( $translatedName ),
			$taxonomy,
			$targetLang,
			$trid
		);
	}

	private function plainName( $name ) {
		return TermWriteRefusal::plainName( $name );
	}

	private function termWriteFailureReason( $error, $sourceTermId, $sourceName, $targetLang ) {
		return TermWriteRefusal::fromWriteError( $error, $sourceTermId, $sourceName, $targetLang );
	}

	private function scopeTermLookupsToLanguage( $taxonomy, $targetLang ) {
		$wpdb = $this->wpdb;

		return function ( $clauses, $taxonomies, $args ) use ( $wpdb, $taxonomy, $targetLang ) {
			if ( [ $taxonomy ] !== array_values( (array) $taxonomies ) ) {
				return $clauses;
			}

			$clauses['join']  .= $wpdb->prepare(
				" LEFT JOIN {$wpdb->prefix}icl_translations wpml_write_lang ON wpml_write_lang.element_id = tt.term_taxonomy_id AND wpml_write_lang.element_type = %s",
				'tax_' . $taxonomy
			);
			$clauses['where'] .= $wpdb->prepare(
				' AND ( wpml_write_lang.language_code = %s OR wpml_write_lang.trid IS NULL )',
				$targetLang
			);

			return $clauses;
		};
	}

	private function keepTermsOfLanguage( $taxonomy, $targetLang ) {
		$sitepress = $this->sitepress;

		return function ( $terms, $taxonomies ) use ( $sitepress, $taxonomy, $targetLang ) {
			if ( ! is_array( $terms ) || [ $taxonomy ] !== array_values( (array) $taxonomies ) ) {
				return $terms;
			}

			foreach ( $terms as $key => $term ) {
				if ( ! $term instanceof \WP_Term || $term->taxonomy !== $taxonomy ) {
					continue;
				}
				$language = $sitepress->get_language_for_element( (int) $term->term_taxonomy_id, 'tax_' . $taxonomy );
				if ( $language && $language !== $targetLang ) {
					unset( $terms[ $key ] );
				}
			}

			return $terms;
		};
	}

	private function clashesOutsideLanguage( $error, $taxonomy, $targetLang ) {
		if ( ! $error instanceof \WP_Error || 'term_exists' !== $error->get_error_code() ) {
			return false;
		}

		$language = $this->languageOfClashingTerm( $error, $taxonomy );

		return null !== $language && '' !== $language && $language !== (string) $targetLang;
	}

	private function languageOfClashingTerm( \WP_Error $error, $taxonomy ) {
		$termId = $error->get_error_data();
		if ( ! is_numeric( $termId ) || (int) $termId <= 0 ) {
			return null;
		}

		$wpdb = $this->wpdb;
		$row  = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT tt.term_taxonomy_id, tr.language_code
				FROM {$wpdb->term_taxonomy} tt
				LEFT JOIN {$wpdb->prefix}icl_translations tr
					ON tr.element_id = tt.term_taxonomy_id AND tr.element_type = %s
				WHERE tt.term_id = %d AND tt.taxonomy = %s",
				'tax_' . $taxonomy,
				(int) $termId,
				$taxonomy
			)
		);

		if ( ! $row ) {
			return null;
		}

		return (string) $row->language_code;
	}

	private function getTargetTermSlug( $trid, $taxonomy, $targetLang ) {
		$wpdb = $this->wpdb;

		return (string) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT t.slug
				FROM {$wpdb->terms} t
				INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
				INNER JOIN {$wpdb->prefix}icl_translations tr ON tr.element_id = tt.term_taxonomy_id
				WHERE tr.trid = %d
					AND tr.element_type = %s
					AND tr.language_code = %s",
				$trid,
				'tax_' . $taxonomy,
				$targetLang
			)
		);
	}

	private function slugTakenInLanguage( $slug, $taxonomy, $targetLang, $trid = 0 ) {
		$wpdb = $this->wpdb;

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				FROM {$wpdb->terms} t
				INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
				LEFT JOIN {$wpdb->prefix}icl_translations tr
					ON tr.element_id = tt.term_taxonomy_id AND tr.element_type = %s
				WHERE t.slug = %s
					AND tt.taxonomy = %s
					AND ( tr.trid IS NULL OR ( tr.language_code = %s AND tr.trid <> %d ) )",
				'tax_' . $taxonomy,
				$slug,
				$taxonomy,
				$targetLang,
				(int) $trid
			)
		);

		return $count > 0;
	}

	private function uniqueSlugInLanguage( $slug, $taxonomy, $targetLang, $trid = 0 ) {
		$slug = (string) sanitize_term_field( 'slug', (string) $slug, 0, $taxonomy, 'db' );

		if ( '' === $slug ) {
			$slug = 'term';
		}

		$base    = $slug;
		$suffix  = 2;
		$current = $slug;

		while ( $this->slugTakenInLanguage( $current, $taxonomy, $targetLang, $trid ) ) {
			$current = $base . '-' . $suffix;
			++$suffix;
		}

		return $current;
	}

	private function resolveTargetLanguage( $attributeLang, $trid, $expectedJobId, $ateJobId ) {
		$jobLang = $this->jobLanguage( $trid, $expectedJobId, $ateJobId );

		if ( null !== $jobLang ) {
			if ( $jobLang !== $attributeLang ) {
				$this->logLanguageMismatch( $attributeLang, $jobLang, $trid, $expectedJobId, $ateJobId );
			}

			return [ $jobLang, $this->findJobId( $trid, $jobLang ) ];
		}

		$jobId = $this->findJobId( $trid, $attributeLang );
		if ( $jobId ) {
			return [ $attributeLang, $jobId ];
		}

		foreach ( $this->wpmlCodesOfAteCode( $attributeLang ) as $candidate ) {
			$candidateJobId = $this->findJobId( $trid, $candidate );
			if ( $candidateJobId ) {
				return [ $candidate, $candidateJobId ];
			}
		}

		return [ $attributeLang, 0 ];
	}

	private function jobLanguage( $trid, $expectedJobId, $ateJobId ) {
		if ( ! $expectedJobId && ! $ateJobId ) {
			return null;
		}

		$wpdb = $this->wpdb;

		$language = $expectedJobId
			? $wpdb->get_var(
				$wpdb->prepare(
					"SELECT t.language_code
					FROM {$wpdb->prefix}icl_translate_job tj
					INNER JOIN {$wpdb->prefix}icl_translation_status ts ON ts.rid = tj.rid
					INNER JOIN {$wpdb->prefix}icl_translations t ON t.translation_id = ts.translation_id
					WHERE tj.job_id = %d AND t.trid = %d",
					(int) $expectedJobId,
					$trid
				)
			)
			: $wpdb->get_var(
				$wpdb->prepare(
					"SELECT t.language_code
					FROM {$wpdb->prefix}icl_translate_job tj
					INNER JOIN {$wpdb->prefix}icl_translation_status ts ON ts.rid = tj.rid
					INNER JOIN {$wpdb->prefix}icl_translations t ON t.translation_id = ts.translation_id
					WHERE tj.editor_job_id = %d AND t.trid = %d
					ORDER BY tj.job_id DESC LIMIT 1",
					(int) $ateJobId,
					$trid
				)
			);

		return is_string( $language ) && '' !== $language ? $language : null;
	}

	private function logLanguageMismatch( $attributeLang, $jobLang, $trid, $expectedJobId, $ateJobId ) {
		$fold = function ( $code ) {
			return str_replace( '_', '-', strtolower( trim( (string) $code ) ) );
		};
		$attribute = $fold( $attributeLang );
		$job       = $fold( $jobLang );

		if ( $attribute === $job ) {
			return;
		}

		$ateCode = $this->ateCodeOf( $jobLang );
		if ( null !== $ateCode ) {
			if ( $fold( $ateCode ) === $attribute ) {
				return;
			}
		} elseif ( 0 === strpos( $attribute, $job . '-' ) ) {
			return;
		}

		JobLog::add(
			'term_delivery_language_differs_from_job',
			[
				'xliff_target_language' => (string) $attributeLang,
				'job_language'          => (string) $jobLang,
				'mapped_ate_code'       => $ateCode,
				'trid'                  => (int) $trid,
				'job_id'                => (int) $expectedJobId,
				'ate_job_id'            => (int) $ateJobId,
				'applied_language'      => (string) $jobLang,
			]
		);
	}

	private function ateCodeOf( $wpmlCode ) {
		foreach ( $this->languageMappings() as $mapping ) {
			if ( (string) $mapping->sourceCode === (string) $wpmlCode && '' !== (string) $mapping->targetCode ) {
				return (string) $mapping->targetCode;
			}
		}

		return null;
	}

	private function wpmlCodesOfAteCode( $ateCode ) {
		$fold = function ( $code ) {
			return str_replace( '_', '-', strtolower( trim( (string) $code ) ) );
		};

		$codes = [];
		foreach ( $this->languageMappings() as $mapping ) {
			if ( '' !== (string) $mapping->sourceCode && $fold( $mapping->targetCode ) === $fold( $ateCode ) ) {
				$codes[] = (string) $mapping->sourceCode;
				break;
			}
		}

		$subtag = explode( '-', $fold( $ateCode ) )[0];
		if ( '' !== $subtag && null === $this->ateCodeOf( $subtag ) ) {
			$codes[] = $subtag;
		}

		return array_values( array_diff( array_unique( $codes ), [ (string) $ateCode ] ) );
	}

	private function languageMappings() {
		if ( null === $this->languageMappings ) {
			try {
				$rows = $this->languageMappingsReader
					? call_user_func( $this->languageMappingsReader )
					: LanguageMappings::get();
			} catch ( \Throwable $e ) {
				$rows = [];
			}
			$this->languageMappings = array_values(
				array_filter(
					is_array( $rows ) ? $rows : [],
					function ( $row ) {
						return is_object( $row ) && isset( $row->sourceCode );
					}
				)
			);
		}

		return $this->languageMappings;
	}

	private function findJobId( $trid, $targetLang ) {
		$wpdb = $this->wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX( tj.job_id )
				FROM {$wpdb->prefix}icl_translate_job tj
				INNER JOIN {$wpdb->prefix}icl_translation_status ts ON ts.rid = tj.rid
				INNER JOIN {$wpdb->prefix}icl_translations t ON t.translation_id = ts.translation_id
				WHERE t.trid = %d AND t.language_code = %s",
				$trid,
				$targetLang
			)
		);
	}

	private function isAwaitingDelivery( $jobId ) {
		if ( ! $jobId ) {
			return true;
		}

		$wpdb = $this->wpdb;

		$status = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ts.status
				 FROM {$wpdb->prefix}icl_translate_job tj
				 INNER JOIN {$wpdb->prefix}icl_translation_status ts ON ts.rid = tj.rid
				 WHERE tj.job_id = %d",
				$jobId
			)
		);

		if ( null === $status ) {
			return true;
		}

		return in_array(
			(int) $status,
			[ ICL_TM_WAITING_FOR_TRANSLATOR, ICL_TM_IN_PROGRESS, ICL_TM_TRANSLATION_READY_TO_DOWNLOAD ],
			true
		);
	}

	private function cancelRefusedDelivery( $trid, $targetLang ) {
		$wpdb = $this->wpdb;

		$translationId = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT translation_id FROM {$wpdb->prefix}icl_translations
				WHERE trid = %d AND language_code = %s ORDER BY ( element_id IS NULL ), translation_id DESC LIMIT 1",
				$trid,
				$targetLang
			)
		);

		if ( ! $translationId ) {
			return;
		}

		$wpdb->update(
			$wpdb->prefix . 'icl_translation_status',
			[ 'status' => ICL_TM_ATE_CANCELLED, 'needs_update' => 0, 'review_status' => null ],
			[ 'translation_id' => $translationId ],
			[ '%d', '%d', '%s' ],
			[ '%d' ]
		);
	}

	private function finalize( $trid, $targetLang, $newTermTaxonomyId, $jobId, $complete = true, $memberRid = 0, $sourceTermId = 0 ) {
		$wpdb = $this->wpdb;

		$translationId = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT translation_id FROM {$wpdb->prefix}icl_translations
				WHERE trid = %d AND language_code = %s AND element_id = %d",
				$trid,
				$targetLang,
				$newTermTaxonomyId
			)
		);

		if ( ! $translationId ) {
			$translationId = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT translation_id FROM {$wpdb->prefix}icl_translations
					WHERE trid = %d AND language_code = %s ORDER BY ( element_id IS NULL ), translation_id DESC LIMIT 1",
					$trid,
					$targetLang
				)
			);
			if ( $translationId ) {
				$wpdb->update(
					$wpdb->prefix . 'icl_translations',
					[ 'element_id' => $newTermTaxonomyId ],
					[ 'translation_id' => $translationId ],
					[ '%d' ],
					[ '%d' ]
				);

				\WPML_Translations::flush_cache_for_trids( [ $trid ] );
			}
		}

		if ( ! $translationId ) {
			return;
		}

		\WPML_Translation_Records_Delete::translations_where(
			'trid = %d AND language_code = %s AND translation_id <> %d',
			[ $trid, $targetLang, $translationId ]
		);

		if ( ! $jobId && ! $memberRid ) {
			return;
		}

		$rid = $memberRid ? (int) $memberRid : $this->ridOfJob( $jobId );
		if ( ! $rid ) {
			return;
		}

		if ( ! $complete ) {
			$wpdb->update(
				$wpdb->prefix . 'icl_translation_status',
				[ 'translation_id' => $translationId ],
				[ 'rid' => $rid ],
				[ '%d' ],
				[ '%d' ]
			);

			return;
		}

		$wpdb->update(
			$wpdb->prefix . 'icl_translation_status',
			[
				'translation_id' => $translationId,
				'status'         => ICL_TM_COMPLETE,
			],
			[ 'rid' => $rid ],
			[ '%d', '%d' ],
			[ '%d' ]
		);

		if ( $sourceTermId ) {
			\WPML\TM\Taxonomy\Job\TermWords::rememberDelivered( (int) $sourceTermId, (string) $targetLang );
		}

		if ( $memberRid ) {
			return;
		}

		$this->markJobTranslated( $jobId );
	}

	private function ridOfJob( $jobId ) {
		$wpdb = $this->wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT rid FROM {$wpdb->prefix}icl_translate_job WHERE job_id = %d", (int) $jobId )
		);
	}

	private function markJobTranslated( $jobId ) {
		$wpdb = $this->wpdb;

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}icl_translate_job
				SET translated = 1, completed_date = COALESCE( completed_date, %s )
				WHERE job_id = %d",
				gmdate( 'Y-m-d H:i:s' ),
				$jobId
			)
		);

		TermJob::onDelivered( $jobId );
	}

	public static function lastFailureReason() {
		return self::$lastFailureReason;
	}

	public static function clearLastFailureReason() {
		self::$lastFailureReason   = '';
		self::$lastDeliveryGivenUp = false;
		self::$lastFailureIsFinal  = false;
		self::$memberFailures      = [];
	}

	public static function lastMemberFailures() {
		return self::$memberFailures;
	}

	public static function lastFailureIsFinal() {
		return self::$lastFailureIsFinal;
	}

	public static function lastDeliveryGivenUp() {
		return self::$lastDeliveryGivenUp;
	}

	private function fail( $reason ) {
		self::$lastFailureReason = (string) $reason;

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'WPML taxonomy term translation could not be applied: ' . $reason );
		}

		return false;
	}
}
