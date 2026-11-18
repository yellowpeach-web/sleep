<?php

namespace WPML\TaxonomyTermTranslation\CopiedTerms;

use WPML\TM\ATE\TranslateEverything\TermBundleMembers;

class CopiedTermsFinder {

	private $wpdb;

	private $sitepress;

	public function __construct( $wpdb, $sitepress ) {
		$this->wpdb      = $wpdb;
		$this->sitepress = $sitepress;
	}

	public function taxonomies() {
		$taxonomies = [];
		foreach ( (array) $this->sitepress->get_translatable_taxonomies( true ) as $taxonomy ) {
			$object = get_taxonomy( $taxonomy );
			if ( $object ) {
				$taxonomies[ (string) $taxonomy ] = (string) $object->labels->name;
			}
		}

		return $taxonomies;
	}

	public function languages() {
		$default   = (string) $this->sitepress->get_default_language();
		$languages = [];
		foreach ( (array) $this->sitepress->get_active_languages() as $code => $language ) {
			if ( $code !== $default ) {
				$languages[ (string) $code ] = (string) ( $language['display_name'] ?? $code );
			}
		}

		return $languages;
	}

	public function find( array $taxonomies = [], array $languages = [] ) {
		$wpdb       = $this->wpdb;
		$active     = array_keys( (array) $this->sitepress->get_active_languages() );
		$taxonomies = $taxonomies ? array_values( array_intersect( $taxonomies, array_keys( $this->taxonomies() ) ) ) : array_keys( $this->taxonomies() );
		$languages  = $languages ? array_values( array_intersect( $languages, $active ) ) : $active;
		if ( ! $taxonomies || ! $languages ) {
			return [];
		}

		TermBundleMembers::ensureTable( $wpdb );

		$rows = [];
		foreach ( $taxonomies as $taxonomy ) {
			$found = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT copy.element_id AS copy_tt_id, copy.language_code, ct.term_id AS copy_term_id, ct.name, ct.slug,
					ctt.count AS objects, orig.element_id AS orig_tt_id, orig.language_code AS orig_language, ot.slug AS orig_slug,
					( SELECT COUNT(*) FROM {$wpdb->prefix}icl_translations member
						WHERE member.trid = copy.trid AND member.element_type = copy.element_type AND member.element_id IS NOT NULL ) AS members,
					( SELECT COUNT(*) FROM {$wpdb->prefix}icl_translations member
						INNER JOIN {$wpdb->term_taxonomy} mtt ON mtt.term_taxonomy_id = member.element_id
						INNER JOIN {$wpdb->terms} mt ON mt.term_id = mtt.term_id
						WHERE member.trid = copy.trid AND member.element_type = copy.element_type
						AND BINARY mt.name <> BINARY ot.name ) AS differing
				FROM {$wpdb->prefix}icl_translations copy
				INNER JOIN {$wpdb->prefix}icl_translations orig
					ON orig.trid = copy.trid AND orig.element_type = copy.element_type AND orig.source_language_code IS NULL
				INNER JOIN {$wpdb->term_taxonomy} ctt ON ctt.term_taxonomy_id = copy.element_id
				INNER JOIN {$wpdb->terms} ct ON ct.term_id = ctt.term_id
				INNER JOIN {$wpdb->term_taxonomy} ott ON ott.term_taxonomy_id = orig.element_id
				INNER JOIN {$wpdb->terms} ot ON ot.term_id = ott.term_id
				LEFT JOIN {$wpdb->prefix}icl_translation_status ts ON ts.translation_id = copy.translation_id
				WHERE copy.element_type = %s
					AND copy.source_language_code IS NOT NULL
					AND copy.language_code IN ( " . implode( ',', array_fill( 0, count( $languages ), '%s' ) ) . " )
					AND BINARY ct.name = BINARY ot.name
					AND ( ct.slug = ot.slug OR ct.slug = CONCAT( ot.slug, '-', copy.language_code ) )
					AND ( ts.rid IS NULL OR (
						ts.status IN ( %d, %d )
						AND NOT EXISTS ( SELECT 1 FROM {$wpdb->prefix}icl_translate_job job WHERE job.rid = ts.rid )
						AND NOT ( ts.status = %d AND EXISTS (
							SELECT 1 FROM {$wpdb->prefix}icl_term_batch_members member_of
							WHERE member_of.term_rid = ts.rid AND member_of.state = %s
						) )
					) )
				ORDER BY copy.language_code, ct.name, copy.element_id",
					array_merge( [ 'tax_' . $taxonomy ], $languages, [ ICL_TM_NOT_TRANSLATED, ICL_TM_COMPLETE, ICL_TM_COMPLETE, TermBundleMembers::STATE_APPLIED ] )
				)
			);

			foreach ( (array) $found as $row ) {
				$rows[] = [
					'taxonomy'         => (string) $taxonomy,
					'language'         => (string) $row->language_code,
					'copyTtId'         => (int) $row->copy_tt_id,
					'copyTermId'       => (int) $row->copy_term_id,
					'name'             => (string) $row->name,
					'slug'             => (string) $row->slug,
					'objects'          => (int) $row->objects,
					'originalTtId'     => (int) $row->orig_tt_id,
					'originalLanguage' => (string) $row->orig_language,
					'originalSlug'     => (string) $row->orig_slug,
					'probablyIntended' => 0 === (int) $row->differing && (int) $row->members >= 3 && (int) $row->members >= count( $active ),
				];
			}
		}

		return $rows;
	}

	public static function countByTaxonomy( array $rows ) {
		$counts = [];
		foreach ( $rows as $row ) {
			$taxonomy = (string) $row['taxonomy'];
			if ( ! isset( $counts[ $taxonomy ] ) ) {
				$counts[ $taxonomy ] = [
					'copies'   => 0,
					'intended' => 0,
				];
			}
			++$counts[ $taxonomy ][ $row['probablyIntended'] ? 'intended' : 'copies' ];
		}

		return $counts;
	}
}
