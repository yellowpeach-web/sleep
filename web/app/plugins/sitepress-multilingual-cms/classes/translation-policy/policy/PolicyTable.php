<?php

namespace WPML\TranslationPolicy\Policy;

use WPML\Upgrade\CreateTable;

class PolicyTable {

	const NAME = 'icl_translation_policy';

	const SCOPE_SITE = 'site';
	const SCOPE_TYPE = 'post_type';
	const SCOPE_POST = 'post';

	const EDITOR_NATIVE = 'native';
	const EDITOR_CTE    = 'cte';
	const EDITOR_ATE    = 'ate';
	const EDITOR_WPML   = 'wpml';

	const COLUMN_EDITOR = 'editor';
	const COLUMN_METHOD = 'method';

	const METHOD_SELF = 'self';
	const METHOD_PTC  = 'ptc';

	const STRENGTH_LOCK    = 'lock';
	const STRENGTH_CHOICE  = 'choice';
	const STRENGTH_DEFAULT = 'default';

	const REASON_MIGRATION = 'migration';

	const SCOPES = [ self::SCOPE_POST, self::SCOPE_TYPE, self::SCOPE_SITE ];

	const STRENGTHS = [ self::STRENGTH_LOCK, self::STRENGTH_CHOICE, self::STRENGTH_DEFAULT ];

	const EDITORS = [ self::EDITOR_NATIVE, self::EDITOR_CTE, self::EDITOR_ATE, self::EDITOR_WPML ];

	const WPML_EDITORS = [ self::EDITOR_CTE, self::EDITOR_ATE ];

	const METHODS = [ self::METHOD_SELF, self::METHOD_PTC ];

	const TYPES_WITHOUT_NATIVE_EDITOR = [ 'wp_navigation' ];

	const FALLBACK_METHOD = self::METHOD_SELF;

	const SCOPE_ID_KEY_LENGTH = 96;

	private $wpdb;

	public function __construct( \wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public static function fallbackEditor(): string {
		return \WPML_TM_ATE_Status::is_enabled() ? self::EDITOR_ATE : self::EDITOR_CTE;
	}

	public function name(): string {
		return $this->wpdb->prefix . self::NAME;
	}

	public function create(): bool {
		return CreateTable::create( $this->wpdb, $this->name(), $this->createSql() );
	}

	public function createSql(): string {
		return 'CREATE TABLE IF NOT EXISTS `' . esc_sql( $this->name() ) . '` (
			`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			`scope` VARCHAR(16) NOT NULL,
			`scope_id` VARCHAR(191) NOT NULL DEFAULT \'\',
			`strength` VARCHAR(16) NOT NULL,
			`source` VARCHAR(64) NOT NULL DEFAULT \'\',
			`editor` VARCHAR(16) NULL DEFAULT NULL,
			`method` VARCHAR(16) NULL DEFAULT NULL,
			`reason` VARCHAR(32) NOT NULL DEFAULT \'\',
			`updated_at` DATETIME NOT NULL,
			PRIMARY KEY (`id`),
			UNIQUE KEY `scope_row` (`scope`, `scope_id`(' . self::SCOPE_ID_KEY_LENGTH . '), `strength`, `source`),
			KEY `scope_lookup` (`scope`, `scope_id`)
		)';
	}
}
