<?php

namespace WPML\TranslationPolicy\Migration;

use WPML\Infrastructure\WordPress\Component\Setting\Application\Service\TranslationEditorStorageFormat;
use WPML\Setup\Option;
use WPML\TranslationPolicy\FeatureSwitch;
use WPML\TranslationPolicy\Policy\PolicyTable;

class LateLegacyWrites {

	const TRANSLATE_EVERYTHING_OPTIONS = [
		'WPML(' . Option::OPTION_GROUP . '/' . Option::TRANSLATE_EVERYTHING . ')',
		'WPML(' . Option::OPTION_GROUP . '/' . Option::TRANSLATE_EVERYTHING_POSTS_SINCE_DATES . ')',
	];

	private $isRunning = false;

	private $accountWrites = 0;

	private $isFullSyncDue = false;

	private $migration;

	public function __construct( ?callable $migration = null ) {
		$this->migration = $migration ?: [ PolicyMigration::class, 'create' ];
	}

	public function turnAccountOn( callable $write ) {
		$this->accountWrites++;
		try {
			return $write();
		} finally {
			$this->accountWrites--;
		}
	}

	public function addHooks() {
		add_action( 'icl_save_settings', [ $this, 'onSettingsSaved' ], PHP_INT_MAX, 0 );
		add_action( 'update_option_icl_sitepress_settings', [ $this, 'onSettingsWritten' ], PHP_INT_MAX, 2 );
		add_action( 'wpml_activated', [ $this, 'onActivated' ], PHP_INT_MAX, 0 );
		add_action( 'shutdown', [ $this, 'onShutdown' ], 0, 0 );
		foreach ( self::TRANSLATE_EVERYTHING_OPTIONS as $option ) {
			add_action( 'add_option_' . $option, [ $this, 'onSettingsSaved' ], PHP_INT_MAX, 0 );
			add_action( 'update_option_' . $option, [ $this, 'onSettingsSaved' ], PHP_INT_MAX, 0 );
			add_action( 'delete_option_' . $option, [ $this, 'onSettingsSaved' ], PHP_INT_MAX, 0 );
		}
	}

	public function onSettingsSaved() {
		if ( $this->isArmed() ) {
			$this->run( fn( PolicyMigration $migration ) => $migration->syncSettings() );
		}
	}

	public function onSettingsWritten( $oldSettings, $newSettings ) {
		$oldKeys = self::editorKeys( $oldSettings );
		$newKeys = self::editorKeys( $newSettings );
		if ( $oldKeys !== $newKeys ) {
			$this->isFullSyncDue = true;
		}

		$editor = self::siteEditor( $newKeys );
		if ( self::siteEditor( $oldKeys ) !== $editor && 0 === $this->accountWrites && $this->isArmed() ) {
			$this->run( fn( PolicyMigration $migration ) => $migration->followSiteEditor( $editor ) );
		}
	}

	private static function siteEditor( array $editorKeys ): string {
		$method = $editorKeys[ TranslationEditorStorageFormat::KEY ] ?? null;

		return TranslationEditorStorageFormat::STORED_ATE === $method ? PolicyTable::EDITOR_ATE : PolicyTable::EDITOR_CTE;
	}

	public function onActivated() {
		if ( $this->isArmed() ) {
			global $wpdb;
			if ( ( new PolicyTable( $wpdb ) )->create() ) {
				$this->run( fn( PolicyMigration $migration ) => $migration->sync() );
			}
		}
	}

	public function onShutdown() {
		if ( $this->isFullSyncDue && $this->isArmed() ) {
			$this->isFullSyncDue = false;
			$this->run( fn( PolicyMigration $migration ) => $migration->sync() );
		}
	}

	private static function editorKeys( $settings ): array {
		$tm = is_array( $settings ) ? ( $settings['translation-management'] ?? null ) : null;
		$tm = is_array( $tm ) ? $tm : [];

		return array_intersect_key( $tm, LegacyEditorStores::TM_KEYS );
	}

	private function isArmed(): bool {
		return ! $this->isRunning && FeatureSwitch::isOn();
	}

	private function run( callable $sync ) {
		$this->isRunning = true;
		try {
			$sync( ( $this->migration )() );
		} finally {
			$this->isRunning = false;
		}
	}
}
