<?php

namespace WPML\Upgrade\Commands;

use WPML\LanguageEditor\Save\Phase\SyncSuspension;
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

	private $upgrade_schema;

	private $sitepress;

	private $result = false;

	public function __construct( array $args ) {
		$this->upgrade_schema = $args[0];
		$this->sitepress      = $args[1];
	}

	private function run() {
		$lock = \WPML\Container\make( \WPML\Utilities\AdvisoryLockFactory::class )->create( 'wpml_4160_remove_priority' );
		if ( ! $lock->acquire( 0 ) ) {
			$this->result = false;

			return false;
		}

		try {
			$this->result = $this->deleteTerms()
				&& $this->deleteOrphanTranslationRows()
				&& $this->forgetTaxonomyInSettings()
				&& $this->dropStringsColumn()
				&& $this->deleteLabelStrings()
				&& $this->deleteChildrenOptions()
				&& $this->retireLegacyCommands();
		} finally {
			$lock->release();
		}

		return $this->result;
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

		$wasRegistered = taxonomy_exists( self::TAXONOMY );
		if ( ! $wasRegistered ) {
			register_taxonomy(
				self::TAXONOMY,
				[],
				[
					'public'    => false,
					'show_ui'   => false,
					'query_var' => false,
					'rewrite'   => false,
				]
			);
		}

		$ok = true;
		try {
			$ok = SyncSuspension::around(
				function () use ( $termIds ) {
					$ok = true;
					foreach ( $termIds as $termId ) {
						$deleted = wp_delete_term( (int) $termId, self::TAXONOMY );
						if ( is_wp_error( $deleted ) ) {
							$ok = false;
						}
					}

					return $ok;
				}
			);
		} finally {
			if ( ! $wasRegistered ) {
				unregister_taxonomy( self::TAXONOMY );
			}
		}

		return $ok;
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
		return $this->run();
	}

	public function run_ajax() {
		return $this->run();
	}

	public function run_frontend() {
		return $this->run();
	}

	public function get_results() {
		return $this->result;
	}
}
