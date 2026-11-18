<?php

namespace WPML\Upgrade\Commands;

use WPML\Upgrade\CommandsStatus;

class RemoveTranslationPriorityTaxonomy implements \IWPML_Upgrade_Command {

	const TAXONOMY     = 'translation_priority';
	const ELEMENT_TYPE = 'tax_translation_priority';
	const TABLE        = 'icl_strings';
	const COLUMN       = 'translation_priority';
	const INDEX        = 'icl_strings_translation_priority';

	const LABEL_STRING_CONTEXT = 'WordPress';
	const LABEL_STRING_NAMES   = [
		'taxonomy general name: Translation Priorities',
		'taxonomy singular name: Translation Priority',
		'URL translation_priority tax slug',
	];

	const LEGACY_TM_COMMAND = 'WPML_TM_Upgrade_Translation_Priorities_For_Posts';
	const LEGACY_ST_COMMAND = 'WPML_ST_Upgrade_DB_Strings_Add_Translation_Priority_Field';

	const BATCH = 5000;

	const TIME_BUDGET = 10;

	const FRONT_END_BUDGET = 2;

	private $upgrade_schema;

	private $sitepress;

	private $result = false;

	private $hasBudgetOverride;

	private $budgetOverride;

	private $onBatch;

	public function __construct( array $args ) {
		$this->upgrade_schema    = $args[0];
		$this->sitepress         = $args[1];
		$this->hasBudgetOverride = array_key_exists( 2, $args );
		$this->budgetOverride    = $this->hasBudgetOverride ? $args[2] : null;
		$this->onBatch           = isset( $args[3] ) && is_callable( $args[3] ) ? $args[3] : null;
	}

	private function run( $budget ) {
		if ( $this->hasBudgetOverride ) {
			$budget = $this->budgetOverride;
		}

		$lock = \WPML\Container\make( \WPML\Utilities\AdvisoryLockFactory::class )->create( 'wpml_4160_remove_priority' );
		if ( ! $lock->acquire( 0 ) ) {
			$this->result = false;

			return false;
		}

		try {
			$independent = [
				$this->dropStringsColumn(),
				$this->deleteLabelStrings(),
				$this->forgetTaxonomyInSettings(),
				$this->deleteChildrenOptions(),
				$this->retireLegacyCommands(),
			];

			$this->result = $this->deleteRelationships( $budget )
				&& $this->deleteTerms()
				&& $this->deleteOrphanTranslationRows()
				&& ! in_array( false, $independent, true );
		} finally {
			$lock->release();
		}

		return $this->result;
	}

	private function deleteRelationships( $budget ) {
		$wpdb = $this->upgrade_schema->get_wpdb();

		$ttIds = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s",
				self::TAXONOMY
			)
		);
		if ( ! $ttIds ) {
			return true;
		}

		$ids        = array_map( 'intval', $ttIds );
		$started    = microtime( true );
		$deletedAny = false;
		$done       = false;

		while ( true ) {
			$deleted = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->term_relationships} WHERE term_taxonomy_id IN (" . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ') LIMIT %d',
					...array_merge( $ids, [ self::BATCH ] )
				)
			);
			if ( false === $deleted ) {
				break;
			}

			$deleted = (int) $deleted;
			if ( $deleted > 0 ) {
				$deletedAny = true;
			}
			if ( $this->onBatch ) {
				call_user_func( $this->onBatch, $deleted );
			}

			if ( $deleted < self::BATCH ) {
				$done = true;
				break;
			}
			if ( null !== $budget && microtime( true ) - $started >= $budget ) {
				break;
			}
		}

		if ( $deletedAny ) {
			$this->forgetTermCaches(
				array_map(
					'intval',
					$wpdb->get_col(
						$wpdb->prepare(
							"SELECT term_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s",
							self::TAXONOMY
						)
					)
				)
			);
		}

		return $done;
	}

	private function deleteTerms() {
		$wpdb = $this->upgrade_schema->get_wpdb();

		$termIds = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT term_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s",
				self::TAXONOMY
			)
		);
		if ( ! $termIds ) {
			return true;
		}

		$ids = array_values( array_unique( array_map( 'intval', $termIds ) ) );

		if ( false === $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", self::TAXONOMY ) ) ) {
			return false;
		}
		$terms = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->terms} WHERE term_id IN (" . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ") AND term_id NOT IN (SELECT term_id FROM {$wpdb->term_taxonomy})", ...$ids ) );
		$meta  = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->termmeta} WHERE term_id IN (" . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ") AND term_id NOT IN (SELECT term_id FROM {$wpdb->terms})", ...$ids ) );

		$this->forgetTermCaches( $ids );

		return false !== $terms && false !== $meta;
	}

	private function forgetTermCaches( array $termIds ) {
		foreach ( $termIds as $termId ) {
			wp_cache_delete( (int) $termId, 'terms' );
		}
		wp_cache_delete( 'all_ids', self::TAXONOMY );
		wp_cache_delete( 'get', self::TAXONOMY );
		wp_cache_set_terms_last_changed();
	}

	private function deleteChildrenOptions() {
		$wpdb = $this->upgrade_schema->get_wpdb();

		$names = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( self::TAXONOMY . '_children' ) . '%'
			)
		);

		foreach ( (array) $names as $name ) {
			delete_option( $name );
		}

		return true;
	}

	private function deleteOrphanTranslationRows() {
		$wpdb = $this->upgrade_schema->get_wpdb();

		$translationIds = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT translation_id FROM {$wpdb->prefix}icl_translations WHERE element_type = %s",
				self::ELEMENT_TYPE
			)
		);
		if ( ! $translationIds ) {
			return true;
		}

		$ids = array_map( 'intval', $translationIds );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}icl_translation_status WHERE translation_id IN (" . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')', ...$ids ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}icl_translations WHERE translation_id IN (" . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')', ...$ids ) );

		return true;
	}

	private function forgetTaxonomyInSettings() {
		$sync = $this->sitepress->get_setting( 'taxonomies_sync_option', [] );
		if ( is_array( $sync ) && array_key_exists( self::TAXONOMY, $sync ) ) {
			unset( $sync[ self::TAXONOMY ] );
			$this->sitepress->set_setting( 'taxonomies_sync_option', $sync, true );
		}

		$tm = $this->sitepress->get_setting( 'translation-management', [] );
		if ( is_array( $tm ) && isset( $tm['taxonomies_readonly_config'] ) && is_array( $tm['taxonomies_readonly_config'] )
			&& array_key_exists( self::TAXONOMY, $tm['taxonomies_readonly_config'] ) ) {
			unset( $tm['taxonomies_readonly_config'][ self::TAXONOMY ] );
			$this->sitepress->set_setting( 'translation-management', $tm, true );
		}

		return true;
	}

	private function dropStringsColumn() {
		if ( ! $this->upgrade_schema->does_table_exist( self::TABLE ) ) {
			return true;
		}

		if ( $this->upgrade_schema->does_index_exist( self::TABLE, self::INDEX ) ) {
			$this->upgrade_schema->drop_index( self::TABLE, self::INDEX );
		}

		if ( $this->upgrade_schema->does_column_exist( self::TABLE, self::COLUMN ) ) {
			$this->upgrade_schema->drop_column( self::TABLE, self::COLUMN );
		}

		return ! $this->upgrade_schema->does_column_exist( self::TABLE, self::COLUMN );
	}

	private function deleteLabelStrings() {
		if ( ! $this->upgrade_schema->does_table_exist( self::TABLE ) ) {
			return true;
		}
		$wpdb = $this->upgrade_schema->get_wpdb();

		$stringIds = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}icl_strings WHERE context = %s AND name IN (%s, %s, %s)",
				self::LABEL_STRING_CONTEXT,
				self::LABEL_STRING_NAMES[0],
				self::LABEL_STRING_NAMES[1],
				self::LABEL_STRING_NAMES[2]
			)
		);
		if ( ! $stringIds ) {
			return true;
		}

		$ids = array_map( 'intval', $stringIds );
		if ( $this->upgrade_schema->does_table_exist( 'icl_string_translations' ) ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}icl_string_translations WHERE string_id IN (" . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')', ...$ids ) );
		}
		if ( $this->upgrade_schema->does_table_exist( 'icl_string_positions' ) ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}icl_string_positions WHERE string_id IN (" . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')', ...$ids ) );
		}
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}icl_strings WHERE id IN (" . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')', ...$ids ) );

		return true;
	}

	private function retireLegacyCommands() {
		( new CommandsStatus() )->clearExecuted( self::LEGACY_TM_COMMAND );

		$st = $this->sitepress->get_setting( 'st', [] );
		if ( is_array( $st ) && isset( $st[ self::LEGACY_ST_COMMAND . '_has_run' ] ) ) {
			unset( $st[ self::LEGACY_ST_COMMAND . '_has_run' ] );
			$this->sitepress->set_setting( 'st', $st, true );
		}

		return true;
	}

	public function run_admin() {
		return $this->run( self::TIME_BUDGET );
	}

	public function run_ajax() {
		return $this->run( self::TIME_BUDGET );
	}

	public function run_frontend() {
		return $this->run( defined( 'WP_CLI' ) && constant( 'WP_CLI' ) ? self::TIME_BUDGET : self::FRONT_END_BUDGET );
	}

	public function get_results() {
		return $this->result;
	}
}
