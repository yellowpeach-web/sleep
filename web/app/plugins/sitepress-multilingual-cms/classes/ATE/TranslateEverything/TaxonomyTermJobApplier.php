<?php

namespace WPML\TM\ATE\TranslateEverything;

use WPML\TM\ATE\JobError\Recorder;
use WPML\TM\ATE\Review\TermJob;
use WPML\TM\Taxonomy\TranslatableTermMeta;
use WPML\TM\XLIFF\TaxonomyTermXliffReader;

class TaxonomyTermJobApplier {

	private $wpdb;

	private $sitepress;

	private $reader;

	private $recorder;

	private static $lastDeliveryGivenUp = false;

	private static $lastFailureReason = '';

	public function __construct( \wpdb $wpdb, \SitePress $sitepress, ?TaxonomyTermXliffReader $reader = null, ?Recorder $recorder = null ) {
		$this->wpdb      = $wpdb;
		$this->sitepress = $sitepress;
		$this->reader    = $reader ?: new TaxonomyTermXliffReader();
		$this->recorder  = $recorder;
	}

	public function apply( $xliff, $expectedJobId = null, $ateJobId = null ) {
		self::clearLastFailureReason();

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
		$targetLang  = $data['targetLang'];

		$trid = (int) $this->sitepress->get_element_trid( (int) $term->term_taxonomy_id, $elementType );
		if ( ! $trid ) {
			return $this->fail( 'no trid for source term_taxonomy_id ' . $term->term_taxonomy_id );
		}

		$jobId = $this->findJobId( $trid, $targetLang );

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

		if ( ! $this->sitepress->is_translated_taxonomy( $taxonomy ) ) {
			$this->cancelRefusedDelivery( $trid, $targetLang );

			return $this->fail(
				"taxonomy '{$taxonomy}' is no longer translatable - delivery refused, job cancelled (wpmldev-8066)"
			);
		}

		$missingUnits = $this->missingUnits( $data, $term, $targetLang );

		$description  = ! in_array( 'term-description', $missingUnits, true ) && isset( $data['fields']['term-description'] )
			? wp_kses_post( $data['fields']['term-description'] )
			: '';
		$isNewTerm    = ! $this->sitepress->term_translations()->element_id_in( (int) $term->term_taxonomy_id, $targetLang );
		$ksesPriority = has_filter( 'pre_term_description', 'wp_filter_kses' );
		remove_filter( 'pre_term_description', 'wp_filter_kses' );
		$slugLookupsInLanguage = $this->scopeSlugLookupsToLanguage( $taxonomy, $targetLang );
		add_filter( 'terms_clauses', $slugLookupsInLanguage, 20, 3 );
		try {
			$action  = new \WPML_Update_Term_Action(
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
			$newTerm = $action->execute();
		} catch ( \Throwable $e ) {
			return $this->fail( 'term write threw: ' . $e->getMessage() );
		} finally {
			remove_filter( 'terms_clauses', $slugLookupsInLanguage, 20 );
			if ( false !== $ksesPriority ) {
				add_filter( 'pre_term_description', 'wp_filter_kses', (int) $ksesPriority );
			}
		}

		if ( empty( $newTerm['term_taxonomy_id'] ) ) {
			return $this->fail( 'term write produced no term_taxonomy_id' );
		}

		$this->finalize( $trid, $targetLang, (int) $newTerm['term_taxonomy_id'], $jobId, ! $missingUnits );

		( new \WPML_Sync_Term_Meta_Action( $this->sitepress, (int) $newTerm['term_taxonomy_id'], $isNewTerm ) )->run();

		$this->applyTranslatedTermMeta( $newTerm, $data['fields'], $term, $targetLang );

		$this->syncTermParent( $taxonomy );

		if ( $missingUnits ) {
			$this->giveUpIncompleteDelivery(
				$jobId,
				$ateJobId,
				sprintf(
					'the delivery lacks the translation of %1$s, which the job sent, for term %2$d (%3$s) into %4$s: the units it carries were written and the job stays not complete',
					implode( ', ', $missingUnits ),
					$sourceTermId,
					$term->name,
					$targetLang
				)
			);
		}

		return $jobId ?: true;
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
			if ( '' !== $unitId && '' === trim( $target ) ) {
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
		return trim( html_entity_decode( (string) $name, ENT_QUOTES, 'UTF-8' ) );
	}

	private function scopeSlugLookupsToLanguage( $taxonomy, $targetLang ) {
		$wpdb = $this->wpdb;

		return function ( $clauses, $taxonomies, $args ) use ( $wpdb, $taxonomy, $targetLang ) {
			if ( empty( $args['slug'] ) || [ $taxonomy ] !== array_values( (array) $taxonomies ) ) {
				return $clauses;
			}

			$clauses['join']  .= $wpdb->prepare(
				" LEFT JOIN {$wpdb->prefix}icl_translations wpml_slug_lang ON wpml_slug_lang.element_id = tt.term_taxonomy_id AND wpml_slug_lang.element_type = %s",
				'tax_' . $taxonomy
			);
			$clauses['where'] .= $wpdb->prepare(
				' AND ( wpml_slug_lang.language_code = %s OR wpml_slug_lang.trid IS NULL )',
				$targetLang
			);

			return $clauses;
		};
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
			[ ICL_TM_WAITING_FOR_TRANSLATOR, ICL_TM_IN_PROGRESS ],
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

	private function finalize( $trid, $targetLang, $newTermTaxonomyId, $jobId, $complete = true ) {
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
			}
		}

		if ( ! $translationId ) {
			return;
		}

		\WPML_Translation_Records_Delete::translations_where(
			'trid = %d AND language_code = %s AND translation_id <> %d',
			[ $trid, $targetLang, $translationId ]
		);

		if ( ! $jobId ) {
			return;
		}

		$rid = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT rid FROM {$wpdb->prefix}icl_translate_job WHERE job_id = %d", $jobId )
		);
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
