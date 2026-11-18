<?php

namespace WPML\TM\ATE\Handshake;

use WPML\Upgrade\CreateTable;

class Schema {

	const LOG_TABLE   = 'icl_ate_handshake_log';
	const MARKS_TABLE = 'icl_ate_handshake_marks';

	private static $ensured = [];

	public static function ensureTables( $wpdb ) {
		$prefix = (string) $wpdb->prefix;

		if ( isset( self::$ensured[ $prefix ] ) ) {
			return self::$ensured[ $prefix ];
		}

		$found = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $prefix . 'icl_ate_handshake_' ) . '%' ) );
		$found = array_map( 'strtolower', is_array( $found ) ? array_map( 'strval', $found ) : [] );

		$exist = in_array( strtolower( $prefix . self::LOG_TABLE ), $found, true )
			&& in_array( strtolower( $prefix . self::MARKS_TABLE ), $found, true );

		self::$ensured[ $prefix ] = $exist || self::createTables( $wpdb );

		return self::$ensured[ $prefix ];
	}

	public static function forgetEnsuredForTests() {
		self::$ensured = [];
	}

	public static function createTables( $wpdb ) {
		$log   = $wpdb->prefix . self::LOG_TABLE;
		$marks = $wpdb->prefix . self::MARKS_TABLE;

		$logCreated = CreateTable::create(
			$wpdb,
			$log,
			'
			CREATE TABLE IF NOT EXISTS `' . esc_sql( $log ) . '` (
			  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			  `t` DATETIME(3) NOT NULL,
			  `kind` VARCHAR(32) NOT NULL,
			  `job_id` BIGINT(20) UNSIGNED NULL DEFAULT NULL,
			  `ate_job_id` BIGINT(20) UNSIGNED NULL DEFAULT NULL,
			  `request_id` VARCHAR(64) NULL DEFAULT NULL,
			  `data` LONGTEXT NULL,
			  PRIMARY KEY (`id`),
			  KEY `t` (`t`),
			  KEY `kind` (`kind`)
			)'
		);

		$marksCreated = CreateTable::create(
			$wpdb,
			$marks,
			'
			CREATE TABLE IF NOT EXISTS `' . esc_sql( $marks ) . '` (
			  `job_id` BIGINT(20) UNSIGNED NOT NULL,
			  `ate_job_id` BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			  `mark` VARCHAR(32) NOT NULL,
			  `marked_at` DATETIME NOT NULL,
			  PRIMARY KEY (`job_id`),
			  KEY `mark` (`mark`)
			)'
		);

		return $logCreated && $marksCreated;
	}
}
