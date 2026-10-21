<?php

namespace WPML\Upgrade\Commands;

use WPML\Core\Component\LanguageEditor\Domain\Bcp47;
use WPML\LanguageEditor\LanguageCodeResolution;

class BackfillBcp47Column implements \IWPML_Upgrade_Command, \IWPML_Pre_Setup_Upgrade_Command {

	const TABLE  = 'icl_languages';
	const COLUMN = 'bcp_47';

	private $schema;

	private $result;

	public function __construct( array $args ) {
		$this->schema = $args[0];
	}

	public function run() {
		if ( \WPML_Settings_Failsafe_Loader::isUnrecoverable() ) {
			return false;
		}

		if ( ! $this->schema->does_column_exist( self::TABLE, self::COLUMN ) ) {
			return false;
		}

		$wpdb  = $this->schema->get_wpdb();
		$table = $wpdb->prefix . self::TABLE;

		$rows = $wpdb->get_results( "SELECT id, code FROM `{$table}` WHERE bcp_47 IS NULL ORDER BY id ASC" );

		foreach ( (array) $rows as $row ) {
			$tag = Bcp47::fromLegacyCode( (string) $row->code );

			if ( '' === $tag ) {
				continue;
			}

			$wpdb->query(
				$wpdb->prepare(
					"UPDATE `{$table}` SET bcp_47 = %s WHERE id = %d AND bcp_47 IS NULL",
					$tag,
					(int) $row->id
				)
			);
		}

		LanguageCodeResolution::resetStoredTags();

		$this->result = true;

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
