<?php

namespace WPML\Upgrade\Commands;

class RemoveObsoleteWordCountMeta implements \IWPML_Upgrade_Command {

	const META_KEY = '_wpml_word_count';

	const BATCH_SIZE = 1000;

	const TIME_BUDGET = 2.0;

	private $schema;

	private $result = false;

	public function __construct( array $args ) {
		$this->schema = $args[0];
	}

	public function run() {
		$wpdb      = $this->schema->get_wpdb();
		$batchSize = $this->batchSize();
		$deadline  = microtime( true ) + $this->timeBudget();

		do {
			$deleted = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s LIMIT %d",
					self::META_KEY,
					$batchSize
				)
			);

			if ( false === $deleted ) {
				$this->result = false;

				return false;
			}
		} while ( $deleted >= $batchSize && microtime( true ) < $deadline );

		$this->result = $deleted < $batchSize;

		return $this->result;
	}

	private function batchSize() {
		$size = (int) apply_filters( 'wpml_upgrade_obsolete_word_count_meta_batch_size', self::BATCH_SIZE );

		return max( 1, $size );
	}

	private function timeBudget() {
		return max( 0.0, (float) apply_filters( 'wpml_upgrade_obsolete_word_count_meta_time_budget', self::TIME_BUDGET ) );
	}

	public function run_admin() {
		return $this->run();
	}

	public function run_ajax() {
		return null;
	}

	public function run_frontend() {
		return null;
	}

	public function get_results() {
		return $this->result;
	}
}
