<?php

namespace WPML\LanguageEditor\Presets;

use WPML\LanguageEditor\Flags\FlagManifest;
use WPML\Upgrade\Commands\CreateCountriesTable;

class CountriesSeeder {

	private $wpdb;

	public function __construct( $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public static function create() {
		global $wpdb;

		return new self( $wpdb );
	}

	public function table() {
		return $this->wpdb->prefix . CreateCountriesTable::TABLE_NAME;
	}

	public static function data() {
		return CountriesData::data();
	}

	public function seed() {
		$table = $this->table();
		$exists = (string) $this->wpdb->get_var( $this->wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( 0 !== strcasecmp( $exists, $table ) ) {
			return -1;
		}

		$rows = self::data();
		if ( ! $rows ) {
			return -1;
		}

		return $this->upsert( $rows );
	}

	public function flagFor( $code ) {
		$cc = strtolower( trim( (string) $code ) );
		if ( '' === $cc ) {
			return '';
		}

		$file = FlagManifest::instance()->countryFile( $cc );

		return null === $file ? '' : $file;
	}

	public function upsert( array $rows ) {
		$written = 0;
		$table   = $this->table();

		foreach ( array_chunk( $rows, 200 ) as $chunk ) {
			$values       = [];
			$placeholders = [];
			foreach ( $chunk as $row ) {
				$code = isset( $row['code'] ) ? strtoupper( trim( (string) $row['code'] ) ) : '';
				if ( '' === $code ) {
					continue;
				}
				$flag = $this->flagFor( $code );
				$name = isset( $row['english_name'] ) ? (string) $row['english_name'] : $code;

				$placeholders[] = '(%s, %s, %s)';
				$values[]       = $code;
				$values[]       = '' === $flag ? null : $flag;
				$values[]       = $name;
			}
			if ( ! $placeholders ) {
				continue;
			}

			$sql = "INSERT INTO `{$table}` (`code`, `flag`, `english_name`)
					VALUES " . implode( ', ', $placeholders ) . '
					ON DUPLICATE KEY UPDATE ' . FlagUpsertRule::fillWhenEmpty( 'flag', true ) . ', `english_name` = VALUES(`english_name`)';

			$result = $this->wpdb->query( $this->wpdb->prepare( $sql, $values ) );
			if ( false !== $result ) {
				$written += min( (int) $result, count( $placeholders ) );
			}
		}

		return $written;
	}
}
