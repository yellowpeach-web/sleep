<?php

namespace WPML\TM\ATE\PullDelivery;

class Meter {

	const COUNTERS = [
		'Questions',
		'Handler_read_key',
		'Handler_read_next',
		'Handler_read_rnd_next',
		'Handler_write',
		'Handler_update',
		'Created_tmp_tables',
	];

	private $before = [];

	private $startedAt = 0.0;

	private $wpQueriesBefore = 0;

	public function start() {
		global $wpdb;

		$this->startedAt       = microtime( true );
		$this->wpQueriesBefore = isset( $wpdb->num_queries ) ? (int) $wpdb->num_queries : 0;
		$this->before          = $this->read();
	}

	public function stop() {
		global $wpdb;

		$after = $this->read();

		$measurement = [
			'seconds'          => round( microtime( true ) - $this->startedAt, 3 ),
			'wp_queries'       => ( isset( $wpdb->num_queries ) ? (int) $wpdb->num_queries : 0 ) - $this->wpQueriesBefore,
			'memory_peak'      => memory_get_peak_usage( false ),
			'memory_peak_real' => memory_get_peak_usage( true ),
		];

		foreach ( self::COUNTERS as $counter ) {
			if ( ! isset( $after[ $counter ], $this->before[ $counter ] ) ) {
				continue;
			}

			$delta = $after[ $counter ] - $this->before[ $counter ];

			if ( 'Questions' === $counter ) {
				$delta = max( 0, $delta - 1 );
			}

			$measurement[ strtolower( $counter ) ] = $delta;
		}

		return $measurement;
	}

	private function read() {
		global $wpdb;

		if ( ! isset( $wpdb ) || ! method_exists( $wpdb, 'get_results' ) ) {
			return [];
		}

		$names = "'" . implode( "','", self::COUNTERS ) . "'";

		$rows = $wpdb->get_results( "SHOW SESSION STATUS WHERE Variable_name IN ( $names )" );

		if ( ! is_array( $rows ) ) {
			return [];
		}

		$counters = [];
		foreach ( $rows as $row ) {
			if ( isset( $row->Variable_name, $row->Value ) ) {
				$counters[ $row->Variable_name ] = (int) $row->Value;
			}
		}

		return $counters;
	}
}
