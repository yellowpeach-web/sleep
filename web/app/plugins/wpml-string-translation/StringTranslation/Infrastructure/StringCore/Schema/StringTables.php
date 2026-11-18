<?php

namespace WPML\StringTranslation\Infrastructure\StringCore\Schema;

use WPML\Upgrade\CreateTable;
use WPML\Upgrade\SchemaFacts;
use WPML\Upgrade\StringTablesSchema;

class StringTables {

	const TABLES = [ 'icl_strings', 'icl_string_translations', 'icl_string_positions', 'icl_string_packages' ];

	const ATTEMPT_OPTION = 'wpml_st_string_tables_create_attempt';

	const RETRY_SECONDS = 300;

	public function missing(): array {
		if ( ! class_exists( SchemaFacts::class ) ) {
			return [];
		}

		return array_values(
			array_filter(
				self::TABLES,
				function ( $table ) {
					return ! SchemaFacts::hasTable( $table );
				}
			)
		);
	}

	public function forgetKnownTables() {
		if ( class_exists( SchemaFacts::class ) ) {
			SchemaFacts::forgetKept();
		}
	}

	public function createMissing(): bool {
		$missing = $this->missing();
		if ( ! $missing ) {
			return true;
		}

		if ( ! $this->claimAttempt() ) {
			return false;
		}

		SchemaFacts::forget();
		$missing = $this->missing();
		if ( ! $missing ) {
			$this->releaseAttempt();

			return true;
		}

		$reason = '';
		if ( class_exists( CreateTable::class ) ) {
			CreateTable::forgetLastRefusal();
		}
		try {
			$this->create( $missing );
		} catch ( \Throwable $e ) {
			$reason = $this->reason( $e );
		}

		SchemaFacts::forget();
		$stillMissing = $this->missing();

		if ( ! $stillMissing ) {
			$this->releaseAttempt();
			$this->log( 'Notice: String Translation\'s missing database tables were created: ' . implode( ', ', $missing ) . '.' );

			return true;
		}

		$this->log(
			sprintf(
				'Error: String Translation\'s database tables %s are missing and could not be created; the next attempt is on an admin page load after %d minutes. Reason: %s',
				implode( ', ', $stillMissing ),
				(int) ( self::RETRY_SECONDS / 60 ),
				'' === $reason ? 'unknown' : $reason
			)
		);

		return false;
	}

	protected function claimAttempt(): bool {
		global $wpdb;

		$now   = time();
		$until = (string) ( $now + self::RETRY_SECONDS );

		$inserted = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", self::ATTEMPT_OPTION, $until ) );
		if ( 1 === (int) $inserted ) {
			return true;
		}

		$held = (string) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::ATTEMPT_OPTION ) );
		if ( (int) $held > $now ) {
			return false;
		}

		$updated = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s", $until, self::ATTEMPT_OPTION, $held ) );

		return 1 === (int) $updated;
	}

	protected function releaseAttempt() {
		global $wpdb;

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s", self::ATTEMPT_OPTION ) );
	}

	protected function create( array $missing ) {
		global $wpdb;

		if ( array_diff( $missing, [ 'icl_string_packages' ] ) ) {
			if ( ! class_exists( StringTablesSchema::class ) ) {
				throw new \RuntimeException( 'this WPML version cannot create the strings tables; update WPML' );
			}
			StringTablesSchema::createMissing( $wpdb );
		}

		if ( in_array( 'icl_string_packages', $missing, true ) ) {
			delete_option( \WPML_Package_Translation_Schema::OPTION_NAME );
			\WPML_Package_Translation_Schema::run_update();
		}
	}

	private function reason( \Throwable $e ): string {
		$refusal = class_exists( CreateTable::class ) ? CreateTable::lastRefusal() : null;
		if ( is_array( $refusal ) ) {
			return $refusal['table'] . ': ' . $refusal['error'];
		}

		return get_class( $e ) . ': ' . $e->getMessage();
	}

	protected function log( string $message ) {
		error_log( '[WPML String Translation] ' . $message . ' (' . self::class . ')' );
	}
}
