<?php

namespace WPML\TM\Upgrade\Commands;

use WPML\TM\Emails\Report\JobRowsStorage;

class MigrateBatchReportToJobRows implements \IWPML_Upgrade_Command {

	const INSERT_CHUNK = 200;

	private $result = false;

	public function __construct( array $args ) {
		unset( $args );
	}

	public function run() {
		$blob = get_option( \WPML_TM_Batch_Report::BATCH_REPORT_OPTION );

		if ( is_array( $blob ) ) {
			$rows = $this->collect_rows( $blob );
			if ( $rows && ! $this->insert_rows( $rows ) ) {
				return false;
			}
		}

		delete_option( \WPML_TM_Batch_Report::BATCH_REPORT_OPTION );

		$this->result = true;
		return $this->result;
	}

	private function collect_rows( array $blob ) {
		$rows = [];

		foreach ( $blob as $translator_id => $language_pairs ) {
			if ( ! is_array( $language_pairs ) ) {
				continue;
			}

			foreach ( $language_pairs as $lang_pair => $items ) {
				if ( ! is_array( $items ) ) {
					continue;
				}

				foreach ( $items as $item ) {
					if ( ! is_array( $item ) || ! isset( $item['job_id'] ) || '' === (string) $item['job_id'] ) {
						continue;
					}

					$job_id = (string) $item['job_id'];
					if ( isset( $rows[ $job_id ] ) ) {
						continue;
					}

					$rows[ $job_id ] = [
						'translator_id' => (int) $translator_id,
						'lang_pair'     => (string) $lang_pair,
						'type'          => isset( $item['type'] ) ? (string) $item['type'] : '',
					];
				}
			}
		}

		return $rows;
	}

	private function insert_rows( array $rows ) {
		global $wpdb;

		foreach ( array_chunk( $rows, self::INSERT_CHUNK, true ) as $chunk ) {
			$values = [];
			foreach ( $chunk as $job_id => $row ) {
				$values[] = JobRowsStorage::ROW_PREFIX . $job_id;
				$values[] = serialize( $row );
			}

			$inserted = $wpdb->query(
				$wpdb->prepare(
					"INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES "
					. implode( ', ', array_fill( 0, count( $chunk ), "(%s, %s, 'off')" ) ),
					$values[0],
					...array_slice( $values, 1 )
				)
			);

			if ( false === $inserted ) {
				return false;
			}
		}

		return true;
	}

	public function run_admin() {
		return $this->run();
	}

	public function run_ajax() {
		return $this->run();
	}

	public function run_frontend() {
		return null;
	}

	public function get_results() {
		return $this->result;
	}
}
