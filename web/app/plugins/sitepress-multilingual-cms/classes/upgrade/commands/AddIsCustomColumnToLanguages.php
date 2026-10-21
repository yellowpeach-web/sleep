<?php

namespace WPML\Upgrade\Commands;

class AddIsCustomColumnToLanguages implements \IWPML_Upgrade_Command, \IWPML_Pre_Setup_Upgrade_Command {

	const TABLE  = 'icl_languages';
	const COLUMN = 'is_custom';

	private $schema;

	public function __construct( array $args ) {
		$this->schema = $args[0];
	}

	private function run() {
		if ( ! $this->schema->does_table_exist( self::TABLE ) ) {
			return true;
		}
		if ( ! $this->schema->does_column_exist( self::TABLE, self::COLUMN ) ) {
			$this->schema->add_column( self::TABLE, self::COLUMN, 'TINYINT NOT NULL DEFAULT 0' );
		}
		$this->backfill();

		return true;
	}

	private function backfill() {
		$builtin = self::builtinCodes();
		if ( empty( $builtin ) ) {
			return;
		}

		$wpdb  = $this->schema->get_wpdb();
		$table = $wpdb->prefix . self::TABLE;
		$in    = wpml_prepare_in( array_keys( $builtin ), '%s' );

		$wpdb->query( "UPDATE `{$table}` SET `" . self::COLUMN . "` = CASE WHEN LOWER(`code`) IN ($in) THEN 0 ELSE 1 END" );
	}

	private static function builtinCodes() {
		if ( ! function_exists( 'icl_get_languages_codes' ) ) {
			return [];
		}

		$builtin = [];
		foreach ( icl_get_languages_codes() as $code ) {
			$builtin[ strtolower( (string) $code ) ] = true;
		}

		return $builtin;
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
		return true;
	}
}
