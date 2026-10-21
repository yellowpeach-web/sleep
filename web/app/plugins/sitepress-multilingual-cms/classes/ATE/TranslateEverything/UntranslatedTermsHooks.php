<?php

namespace WPML\TM\ATE\TranslateEverything;

use WPML\LIB\WP\Hooks;
use WPML\Setup\Option;
use WPML\TM\XLIFF\TaxonomyTermXliffBuilder;
use function WPML\Container\make;
use function WPML\FP\spreadArgs;

class UntranslatedTermsHooks implements \IWPML_Backend_Action, \IWPML_REST_Action, \IWPML_AJAX_Action {

	public function add_hooks() {
		Hooks::onFilter( 'wpml_translate_everything_untranslated_elements_strategies' )
			->then( spreadArgs( [ $this, 'registerStrategy' ] ) );

		Hooks::onAction( 'wpml_taxonomy_made_translatable', 10, 1 )
			->then( spreadArgs( [ $this, 'onTaxonomyMadeTranslatable' ] ) );

		Hooks::onAction( 'wpml_taxonomy_made_not_translatable', 10, 1 )
			->then( spreadArgs( [ $this, 'onTaxonomyMadeNotTranslatable' ] ) );

		Hooks::onAction( 'created_term', 10, 3 )
			->then( spreadArgs( [ $this, 'onTermCreated' ] ) );

		Hooks::onAction( 'edited_term', 10, 3 )
			->then( spreadArgs( [ $this, 'onTermEdited' ] ) );

		Hooks::onAction( 'added_term_meta', 10, 3 )
			->then( spreadArgs( [ $this, 'onTermMetaChanged' ] ) );

		Hooks::onAction( 'updated_term_meta', 10, 3 )
			->then( spreadArgs( [ $this, 'onTermMetaChanged' ] ) );

		Hooks::onAction( 'deleted_term_meta', 10, 3 )
			->then( spreadArgs( [ $this, 'onTermMetaChanged' ] ) );

		Hooks::onAction( 'pre_delete_term', 10, 2 )
			->then( spreadArgs( [ $this, 'onPreDeleteTerm' ] ) );

		Hooks::onAction( 'wpml_taxonomy_term_content_changed', 10, 1 )
			->then( spreadArgs( [ $this, 'onTermContentChanged' ] ) );
	}

	public function registerStrategy( array $strategies ): array {
		$strategies[] = make( UntranslatedTerms::class );

		return $strategies;
	}

	public function onTaxonomyMadeTranslatable( string $taxonomy ) {
		if ( ! Option::shouldTranslateEverything() ) {
			return;
		}

		make( UntranslatedTerms::class )->markTaxonomyAsUncompleted( $taxonomy );
	}

	public function onTaxonomyMadeNotTranslatable( string $taxonomy ) {
		global $wpdb;

		( new \WPML\TM\Taxonomy\Job\TermJobRowFactory( $wpdb ) )
			->cancelInFlightForTaxonomy( $taxonomy );
	}

	public function onTermCreated( $term_id, $tt_id, $taxonomy ) {
		if ( ! Option::shouldTranslateEverything() ) {
			return;
		}

		global $sitepress;

		if ( ! $sitepress || ! $sitepress->is_translated_taxonomy( $taxonomy ) ) {
			return;
		}

		make( UntranslatedTerms::class )->markTaxonomyAsUncompleted( $taxonomy );
	}

	public function onTermEdited( $term_id, $tt_id, $taxonomy ) {
		global $sitepress;

		if ( ! $sitepress || ! $sitepress->is_translated_taxonomy( $taxonomy ) ) {
			return;
		}

		$this->flagTranslationsNeedUpdate( (int) $tt_id, (string) $taxonomy );
	}

	public function onTermMetaChanged( $meta_id, $term_id, $meta_key ) {
		global $sitepress, $wpdb;

		if ( ! $sitepress || ! $wpdb ) {
			return;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT term_taxonomy_id, taxonomy FROM {$wpdb->term_taxonomy} WHERE term_id = %d",
				(int) $term_id
			)
		);

		if ( ! $row || ! $sitepress->is_translated_taxonomy( $row->taxonomy ) ) {
			return;
		}

		if ( ! \WPML\TM\Taxonomy\TranslatableTermMeta::isTranslatableKey( (string) $meta_key, (string) $row->taxonomy ) ) {
			return;
		}

		$this->flagTranslationsNeedUpdate( (int) $row->term_taxonomy_id, (string) $row->taxonomy );
	}

	public function onTermContentChanged( $term_id ) {
		global $sitepress, $wpdb;

		if ( ! $sitepress || ! $wpdb || ! $term_id ) {
			return;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT term_taxonomy_id, taxonomy FROM {$wpdb->term_taxonomy} WHERE term_id = %d",
				(int) $term_id
			)
		);

		if ( ! $row || ! $sitepress->is_translated_taxonomy( $row->taxonomy ) ) {
			return;
		}

		$this->flagTranslationsNeedUpdate( (int) $row->term_taxonomy_id, (string) $row->taxonomy );
	}

	private function flagTranslationsNeedUpdate( $tt_id, $taxonomy ) {
		global $wpdb;

		$elementType = UntranslatedTerms::ELEMENT_TYPE_PREFIX . $taxonomy;

		$source = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT trid, language_code, source_language_code
					FROM {$wpdb->prefix}icl_translations
					WHERE element_type = %s AND element_id = %d",
				$elementType,
				(int) $tt_id
			)
		);

		if ( ! $source || null !== $source->source_language_code ) {
			return;
		}

		$md5 = $this->sourceChecksum( (int) $tt_id, (string) $taxonomy );

		if ( ! Option::shouldTranslateEverything() ) {
			$this->addMissingStatusRows( (int) $source->trid, $md5 );
		}

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}icl_translation_status ts
					INNER JOIN {$wpdb->prefix}icl_translations t
						ON t.translation_id = ts.translation_id
					SET ts.needs_update = 1, ts.md5 = %s
					WHERE t.trid = %d
					AND t.language_code <> %s",
				$md5,
				(int) $source->trid,
				(string) $source->language_code
			)
		);

		make( UntranslatedTerms::class )->markTaxonomyAsUncompleted( $taxonomy );
	}

	private function sourceChecksum( int $tt_id, string $taxonomy ): string {
		$disable = '__return_true';
		add_filter( 'wpml_disable_term_adjust_id', $disable, 999 );
		try {
			$term = get_term_by( 'term_taxonomy_id', $tt_id, $taxonomy );
		} finally {
			remove_filter( 'wpml_disable_term_adjust_id', $disable, 999 );
		}

		if ( ! $term instanceof \WP_Term || (int) $term->term_taxonomy_id !== $tt_id ) {
			return '';
		}

		return make( TaxonomyTermXliffBuilder::class )->sourceChecksum( $term );
	}

	private function addMissingStatusRows( int $trid, string $md5 ) {
		global $wpdb;

		$translationIds = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT t.translation_id
					FROM {$wpdb->prefix}icl_translations t
					INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = t.element_id
					LEFT JOIN {$wpdb->prefix}icl_translation_status ts ON ts.translation_id = t.translation_id
					WHERE t.trid = %d
					AND t.source_language_code IS NOT NULL
					AND t.element_id IS NOT NULL
					AND ts.translation_id IS NULL",
				$trid
			)
		);

		foreach ( (array) $translationIds as $translationId ) {
			$wpdb->insert(
				$wpdb->prefix . 'icl_translation_status',
				[
					'translation_id'      => (int) $translationId,
					'status'              => ICL_TM_COMPLETE,
					'translation_service' => 'local',
					'translator_id'       => 0,
					'batch_id'            => 0,
					'needs_update'        => 0,
					'md5'                 => $md5,
					'translation_package' => '',
				],
				[ '%d', '%d', '%s', '%d', '%d', '%d', '%s', '%s' ]
			);
		}
	}

	public function onPreDeleteTerm( $term_id, $taxonomy ) {
		global $sitepress, $wpdb;

		if ( ! $sitepress || ! $sitepress->is_translated_taxonomy( $taxonomy ) ) {
			return;
		}

		$elementType = UntranslatedTerms::ELEMENT_TYPE_PREFIX . $taxonomy;

		$ttId = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id = %d AND taxonomy = %s",
				(int) $term_id,
				$taxonomy
			)
		);
		if ( ! $ttId ) {
			return;
		}

		$source = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT trid, source_language_code
					FROM {$wpdb->prefix}icl_translations
					WHERE element_type = %s AND element_id = %d",
				$elementType,
				$ttId
			)
		);

		if ( ! $source || null !== $source->source_language_code ) {
			return;
		}

		$this->deleteOrphanInProgressTranslations( (int) $source->trid );
	}

	private function deleteOrphanInProgressTranslations( int $trid ) {
		global $wpdb;

		$translationIds = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT translation_id FROM {$wpdb->prefix}icl_translations
					WHERE trid = %d AND element_id IS NULL",
				$trid
			)
		);

		if ( empty( $translationIds ) ) {
			return;
		}

		$translationIds = array_map( 'intval', $translationIds );

		\WPML_Translation_Records_Delete::jobs_by_translation_ids( $translationIds );
		\WPML_Translation_Records_Delete::translations_by_ids( $translationIds );
	}
}
