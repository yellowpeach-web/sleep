<?php

namespace WPML\Upgrade;

class TranslationStatusSchema {

	const TABLE = 'icl_translation_status';

	const COLUMNS = [
		'uuid'                 => 'VARCHAR(36) NULL',
		'tp_id'                => 'INT NULL DEFAULT NULL',
		'tp_revision'          => 'INT NOT NULL DEFAULT 1',
		'ts_status'            => 'TEXT NULL DEFAULT NULL',
		'review_status'        => "ENUM('NEEDS_REVIEW', 'EDITING', 'ACCEPTED')",
		'ate_comm_retry_count' => 'INT(11) UNSIGNED DEFAULT 0',
	];

	public static function healMissingColumns(): bool {
		global $wpdb;

		return ( new \WPML_Upgrade_Schema( $wpdb ) )->ensure_columns( self::TABLE, self::COLUMNS );
	}
}
