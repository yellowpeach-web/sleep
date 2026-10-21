<?php

namespace WPML\Upgrade\Commands;

use WPML\Infrastructure\WordPress\Component\CustomFieldPreferences\Repository\PreferenceRepository;
use WPML\Infrastructure\WordPress\Component\CustomFieldPreferences\StoreReadiness;

class AddNameHashToMetaSettings implements \IWPML_Upgrade_Command {

	const BACKFILL_BATCH = 500;

	private $result = false;

	public function __construct( array $args ) {
		unset( $args );
	}

	public function run() {
		global $wpdb;

		$table = $wpdb->prefix . PreferenceRepository::TABLE;

		if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
			$this->result = true;
			return $this->result;
		}

		$hasColumn = false;
		foreach ( (array) $wpdb->get_results( "SHOW COLUMNS FROM `{$table}`", ARRAY_A ) as $column ) {
			if ( isset( $column['Field'] ) && 'name_hash' === $column['Field'] ) {
				$hasColumn = true;
				break;
			}
		}

		if ( ! $hasColumn && $wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `name_hash` CHAR(40) NOT NULL DEFAULT ''" ) === false ) {
			return false;
		}

		if ( ! $this->backfillHashes( $wpdb, $table ) ) {
			return false;
		}

		$keys = [];
		foreach ( (array) $wpdb->get_results( "SHOW INDEX FROM `{$table}`", ARRAY_A ) as $index ) {
			if ( isset( $index['Key_name'] ) ) {
				$keys[ (string) $index['Key_name'] ] = true;
			}
		}

		if ( ! isset( $keys['type_name_hash'] ) ) {
			$steps = [];
			if ( isset( $keys['type_name'] ) ) {
				$steps[] = 'DROP INDEX `type_name`';
			}
			if ( ! isset( $keys['type_name_lookup'] ) ) {
				$steps[] = 'ADD KEY `type_name_lookup` (`element_type`, `name`(191))';
			}
			$steps[] = 'ADD UNIQUE KEY `type_name_hash` (`element_type`, `name_hash`)';

			if ( $wpdb->query( "ALTER TABLE `{$table}` " . implode( ', ', $steps ) ) === false ) {
				return false;
			}
		}

		StoreReadiness::forget();

		$this->result = true;
		return $this->result;
	}

	private function backfillHashes( $wpdb, $table ) {
		$lastId = 0;

		do {
			$rows = (array) $wpdb->get_results(
				$wpdb->prepare(
					"SELECT `id`, `name` FROM `{$table}` WHERE `name_hash` = '' AND `id` > %d ORDER BY `id` ASC LIMIT %d",
					$lastId,
					self::BACKFILL_BATCH
				),
				ARRAY_A
			);
			$read = count( $rows );

			foreach ( $rows as $row ) {
				$id     = (int) $row['id'];
				$lastId = max( $lastId, $id );

				$updated = $wpdb->query(
					$wpdb->prepare(
						"UPDATE `{$table}` SET `name_hash` = %s WHERE `id` = %d",
						PreferenceRepository::nameHash( (string) $row['name'] ),
						$id
					)
				);

				if ( false === $updated ) {
					return false;
				}
			}
		} while ( $read === self::BACKFILL_BATCH );

		$remaining = $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}` WHERE `name_hash` = ''" );

		return null !== $remaining && 0 === (int) $remaining;
	}

	public function run_admin() {
		return $this->run();
	}

	public function run_ajax() {
		return null;
	}

	public function run_frontend() {
		return null;
	}

	public function get_results() {
		return $this->result;
	}
}
