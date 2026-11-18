<?php

namespace WPML\ST\Upgrade\Command;

use WPML\Upgrade\SchemaFacts;

class AddStringPackageOwnerColumns implements \IWPML_St_Upgrade_Command {

	private $wpdb;

	public function __construct( \wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function run() {
		if ( ! SchemaFacts::hasTable( 'icl_string_packages' ) ) {
			return false;
		}

		$wpdb   = $this->wpdb;
		$fields = array_map(
			function ( $column ) {
				return $column->Field;
			},
			(array) $wpdb->get_results(
				$wpdb->prepare( "SHOW COLUMNS FROM `{$wpdb->prefix}icl_string_packages` LIKE %s", 'owner\_%' )
			)
		);
		$alters = [];
		if ( ! in_array( 'owner_kind', $fields, true ) ) {
			$alters[] = "ADD COLUMN `owner_kind` VARCHAR(8) NULL DEFAULT NULL";
		}
		if ( ! in_array( 'owner_element_id', $fields, true ) ) {
			$alters[] = 'ADD COLUMN `owner_element_id` BIGINT UNSIGNED NULL DEFAULT NULL';
		}
		$index = $wpdb->get_results( $wpdb->prepare( "SHOW INDEX FROM `{$wpdb->prefix}icl_string_packages` WHERE Key_name = %s", 'owner' ) );
		if ( ! $index ) {
			$alters[] = 'ADD KEY `owner` (`owner_kind`, `owner_element_id`)';
		}

		$altered = 0;
		if ( $alters ) {
			$schema     = wpml_get_upgrade_schema();
			$attributes = 0 === strpos( $alters[0], 'ADD COLUMN `owner_kind`' ) ? 'VARCHAR(8) NULL DEFAULT NULL' : 'BIGINT UNSIGNED NULL DEFAULT NULL';
			if ( count( $alters ) > 1 ) {
				$attributes .= ', ' . implode( ', ', array_slice( $alters, 1 ) );
			}
			if ( 0 === strpos( $alters[0], 'ADD COLUMN `owner_kind`' ) ) {
				$altered = $schema->add_column( 'icl_string_packages', 'owner_kind', $attributes );
			} elseif ( 0 === strpos( $alters[0], 'ADD COLUMN `owner_element_id`' ) ) {
				$altered = $schema->add_column( 'icl_string_packages', 'owner_element_id', $attributes );
			} else {
				$altered = $schema->add_index( 'icl_string_packages', 'owner', '( `owner_kind`, `owner_element_id` )' );
			}
		}

		if ( false === $altered ) {
			$this->logFailure( 'ALTER' );
			SchemaFacts::forget();
			return false;
		}

		SchemaFacts::forget();

		$backfill = $wpdb->query(
			$wpdb->prepare(
				"UPDATE `{$wpdb->prefix}icl_string_packages`
				SET owner_kind = %s, owner_element_id = post_id
				WHERE post_id IS NOT NULL AND owner_element_id IS NULL",
				'post'
			)
		);
		if ( false === $backfill ) {
			$this->logFailure( 'backfill' );
			return false;
		}

		return true;
	}

	private function logFailure( $operation ) {
		error_log( 'WPML String Translation: string package owner ' . $operation . ' failed: ' . $this->wpdb->last_error );
	}

	public function run_ajax() {
		return false;
	}

	public function run_frontend() {
		return false;
	}

	public static function get_command_id() {
		return __CLASS__;
	}
}
