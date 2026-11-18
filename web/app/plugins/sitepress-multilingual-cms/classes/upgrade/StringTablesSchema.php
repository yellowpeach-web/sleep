<?php

namespace WPML\Upgrade;

class StringTablesSchema {

	const TABLES = [ 'icl_strings', 'icl_string_translations', 'icl_string_positions' ];

	public static function createMissing( $wpdb ) {
		foreach ( self::TABLES as $table ) {
			$tableName  = $wpdb->prefix . $table;
			$foundTable = (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tableName ) );
			if ( 0 === strcasecmp( $foundTable, $tableName ) ) {
				continue;
			}

			if ( ! self::create( $wpdb, $table ) ) {
				throw new \Exception( esc_html( $wpdb->last_error ) );
			}
		}
	}

	private static function create( $wpdb, $table ) {
		switch ( $table ) {
			case 'icl_strings':
				return CreateTable::create(
					$wpdb,
					$wpdb->prefix . 'icl_strings',
					"
	                 CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}icl_strings` (
	                  `id` bigint(20) unsigned NOT NULL auto_increment,
	                  `language` varchar(7) NOT NULL,
	                  `context` varchar(" . absint( WPML_STRING_TABLE_NAME_CONTEXT_LENGTH ) . ') NOT NULL,
	                  `name` varchar(' . absint( WPML_STRING_TABLE_NAME_CONTEXT_LENGTH ) . ") NOT NULL,
                  `value` longtext NOT NULL,
                  `string_package_id` BIGINT unsigned NULL,
                  `location` BIGINT unsigned NULL,
                  `wrap_tag` VARCHAR( 16 ) NOT NULL,
                  `type` VARCHAR(40) NOT NULL DEFAULT 'LINE',
                  `title` TEXT NULL,
                  `status` TINYINT NOT NULL,
                  `gettext_context` TEXT NOT NULL,
                  `domain_name_context_md5` VARCHAR(32) NOT NULL,
                  `word_count` int unsigned NULL,
                  `has_text` TINYINT(1) NOT NULL DEFAULT 1,
                  PRIMARY KEY  (`id`),
                  UNIQUE KEY `uc_domain_name_context_md5` (`domain_name_context_md5`),
                  KEY `language_context` (`language`, `context`),
                  KEY `icl_strings_name` (`name` ASC),
                  KEY `context` ( `context` ),
                  KEY `context_has_text` ( `context`, `has_text` ),
                  KEY `string_package_id` ( `string_package_id` )
	                  )"
				);

			case 'icl_string_translations':
				return CreateTable::create(
					$wpdb,
					$wpdb->prefix . 'icl_string_translations',
					"
	                 CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}icl_string_translations` (
                  `id` bigint(20) unsigned NOT NULL auto_increment,
                  `string_id` bigint(20) unsigned NOT NULL,
                  `language` varchar(10) NOT NULL,
                  `status` tinyint(4) NOT NULL,
                  `value` longtext NULL DEFAULT NULL,
                  `mo_string` longtext NULL DEFAULT NULL,
                  `translator_id` bigint(20) unsigned DEFAULT NULL,
                  `translation_service` varchar(16) DEFAULT '' NOT NULL,
                  `batch_id` int DEFAULT 0 NOT NULL,
                  `translation_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  PRIMARY KEY  (`id`),
                  UNIQUE KEY `string_language` (`string_id`,`language`)
	                )"
				);

			case 'icl_string_positions':
				return CreateTable::create(
					$wpdb,
					$wpdb->prefix . 'icl_string_positions',
					"
	                  CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}icl_string_positions` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY ,
                `string_id` BIGINT NOT NULL ,
                `kind` TINYINT,
                `position_in_page` VARCHAR( 255 ) NOT NULL,
                INDEX ( `string_id` )
	                )"
				);
		}

		throw new \InvalidArgumentException( 'StringTablesSchema has no statement for this table.' );
	}
}
