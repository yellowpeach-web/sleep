<?php

namespace WPML\TM\Emails\Report;

class JobRowsStorage {

	const ROW_PREFIX = '_wpml_batch_report_job_';

	private $wpdb;

	public function __construct( \wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function getAll(): array {
		$wpdb = $this->wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( self::ROW_PREFIX ) . '%'
			)
		);

		$jobs = [];
		foreach ( (array) $rows as $row ) {
			$value = maybe_unserialize( $row->option_value );
			if ( ! is_array( $value ) ) {
				continue;
			}
			$jobs[ substr( $row->option_name, strlen( self::ROW_PREFIX ) ) ] = $value;
		}

		return $jobs;
	}

	public function upsert( $jobId, int $translatorId, string $langPair, string $type ): void {
		update_option(
			self::ROW_PREFIX . $jobId,
			[
				'translator_id' => $translatorId,
				'lang_pair'     => $langPair,
				'type'          => $type,
			],
			'no'
		);
	}

	public function deleteByJobIds( array $jobIds ): void {
		if ( ! $jobIds ) {
			return;
		}

		$names = array_map( fn( $jobId ) => self::ROW_PREFIX . $jobId, array_values( $jobIds ) );
		$wpdb  = $this->wpdb;

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name IN ("
				. implode( ', ', array_fill( 0, count( $names ), '%s' ) ) . ')',
				...$names
			)
		);

		foreach ( $names as $name ) {
			wp_cache_delete( $name, 'options' );
		}
	}

	public function deleteAll(): void {
		$wpdb  = $this->wpdb;
		$names = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( self::ROW_PREFIX ) . '%'
			)
		);

		$this->deleteByJobIds( array_map(
			fn( $name ) => substr( $name, strlen( self::ROW_PREFIX ) ),
			(array) $names
		) );
	}

	public function deleteByTranslatorIds( array $translatorIds ): void {
		if ( ! $translatorIds ) {
			return;
		}

		$translatorIds = array_map( 'intval', $translatorIds );

		$jobIds = array_keys(
			array_filter(
				$this->getAll(),
				fn( $row ) => in_array( (int) $row['translator_id'], $translatorIds, true )
			)
		);

		$this->deleteByJobIds( $jobIds );
	}
}
