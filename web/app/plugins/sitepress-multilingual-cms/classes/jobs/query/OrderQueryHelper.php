<?php

namespace WPML\TM\Jobs\Query;

use \WPML_TM_Jobs_Search_Params;

class OrderQueryHelper {

	const QUEUE_PRIORITY = 'queue_priority';

	private $currentUserIdProvider;

	public function __construct( $currentUserIdProvider = null ) {
		if ( is_callable( $currentUserIdProvider ) ) {
			$this->currentUserIdProvider = $currentUserIdProvider;
		} else {
			$currentUserId               = (int) $currentUserIdProvider;
			$this->currentUserIdProvider = function () use ( $currentUserId ) {
				return $currentUserId;
			};
		}
	}

	public function get_order( \WPML_TM_Jobs_Search_Params $params ) {
		$orders = $this->map_sort_parameters( $params );

		if ( $orders ) {
			return 'ORDER BY ' . implode( ', ', $orders );
		} else {
			return '';
		}
	}

	private function get_expressions_by_key() {
		$currentUserId = (int) call_user_func( $this->currentUserIdProvider );

		return array(
			'id'                 => array( 'id' ),
			'title'              => array( 'title' ),
			'status'             => array( 'status' ),
			'batch_name'         => array( 'batch_name' ),
			'translator_id'      => array( 'translator_id' ),
			'translate_job_id'   => array( 'translate_job_id' ),
			'job_id'             => array( 'translate_job_id' ),
			'language'           => array( 'source_language_name', 'target_language_name' ),
			'sent_date'          => array( 'DATE(sent_date)' ),
			'deadline_date'      => array( 'DATE(deadline_date)' ),
			self::QUEUE_PRIORITY => array( 'IF (status = 2 AND translator_id = ' . $currentUserId . ", 0, IF ((status = 10 AND (review_status = 'EDITING' OR review_status = 'NEEDS_REVIEW')) OR status = 1, 1,IF (status = 2, 2, IF (needs_update = 1, 3, 4))))" ),
		);
	}

	private function map_sort_parameters( WPML_TM_Jobs_Search_Params $params ) {
		$orders = array();
		if ( $params->get_sorting() ) {
			$expressions_by_key = $this->get_expressions_by_key();

			foreach ( $params->get_sorting() as $order ) {
				$key = $order->get_column();
				if ( ! is_string( $key ) || ! isset( $expressions_by_key[ $key ] ) ) {
					continue;
				}

				$direction = 'desc' === strtolower( $order->get_direction() ) ? 'desc' : 'asc';

				foreach ( $expressions_by_key[ $key ] as $expression ) {
					$orders[] = $expression . ' ' . $direction;
				}
			}
		}

		return $orders;
	}

}
