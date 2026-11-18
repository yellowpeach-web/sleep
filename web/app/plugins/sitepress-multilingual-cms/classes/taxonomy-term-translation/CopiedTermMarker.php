<?php

namespace WPML\TaxonomyTermTranslation;

use WPML\Setup\Option;
use WPML\TM\ATE\TranslateEverything\UntranslatedTerms;
use function WPML\Container\make;

class CopiedTermMarker implements \IWPML_Backend_Action, \IWPML_Frontend_Action, \IWPML_DIC_Action {

	const ACTION = 'wpml_mark_copied_term';

	public function add_hooks() {
		add_action( self::ACTION, [ self::class, 'mark' ], 10, 2 );
		add_action( 'edited_term', [ self::class, 'onTermEdited' ], 10, 3 );
	}

	public static function mark( $termTaxonomyId, $taxonomy ) {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT t.translation_id, ts.rid
					FROM {$wpdb->prefix}icl_translations t
					LEFT JOIN {$wpdb->prefix}icl_translation_status ts ON ts.translation_id = t.translation_id
					WHERE t.element_id = %d AND t.element_type = %s AND t.source_language_code IS NOT NULL",
				(int) $termTaxonomyId,
				'tax_' . $taxonomy
			)
		);

		if ( ! $row || null !== $row->rid ) {
			return;
		}

		$written = $wpdb->insert(
			$wpdb->prefix . 'icl_translation_status',
			[
				'translation_id'      => (int) $row->translation_id,
				'status'              => ICL_TM_NOT_TRANSLATED,
				'translation_service' => 'local',
				'translator_id'       => 0,
				'batch_id'            => 0,
				'needs_update'        => 0,
				'md5'                 => '',
				'translation_package' => '',
			],
			[ '%d', '%d', '%s', '%d', '%d', '%d', '%s', '%s' ]
		);

		if ( false === $written ) {
			return;
		}

		if ( Option::shouldTranslateEverything() ) {
			make( UntranslatedTerms::class )->markTaxonomyAsUncompleted( (string) $taxonomy );
		}
	}

	public static function onTermEdited( $termId, $termTaxonomyId, $taxonomy ) {
		global $wpdb;

		$mark = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT ts.rid, copy_term.name AS copy_name, original_term.name AS original_name
					FROM {$wpdb->prefix}icl_translations t
					INNER JOIN {$wpdb->prefix}icl_translation_status ts ON ts.translation_id = t.translation_id
					INNER JOIN {$wpdb->prefix}icl_translations original
						ON original.trid = t.trid AND original.source_language_code IS NULL
					INNER JOIN {$wpdb->term_taxonomy} copy_tt ON copy_tt.term_taxonomy_id = t.element_id
					INNER JOIN {$wpdb->terms} copy_term ON copy_term.term_id = copy_tt.term_id
					INNER JOIN {$wpdb->term_taxonomy} original_tt ON original_tt.term_taxonomy_id = original.element_id
					INNER JOIN {$wpdb->terms} original_term ON original_term.term_id = original_tt.term_id
					WHERE t.element_id = %d AND t.element_type = %s AND t.source_language_code IS NOT NULL
					AND ts.status = %d AND ts.needs_update = 0
					AND NOT EXISTS ( SELECT 1 FROM {$wpdb->prefix}icl_translate_job job WHERE job.rid = ts.rid )",
				(int) $termTaxonomyId,
				'tax_' . $taxonomy,
				ICL_TM_NOT_TRANSLATED
			)
		);

		if ( ! $mark || (string) $mark->copy_name === (string) $mark->original_name ) {
			return;
		}

		$wpdb->delete( $wpdb->prefix . 'icl_translation_status', [ 'rid' => (int) $mark->rid ], [ '%d' ] );
	}
}
