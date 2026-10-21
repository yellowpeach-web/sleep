<?php

namespace WPML\TM\Upgrade\Commands;

use WPML\Core\BackgroundTask\Model\BackgroundTask;
use WPML\TM\Settings\CustomFieldChangeDetector;
use WPML\TM\Settings\ProcessNewTranslatableFields;
use WPML\TM\Settings\Repository;

class RetireUnaskedFieldReprocessingTasks implements \IWPML_Upgrade_Command {

	const RESULT_OPTION = 'wpml_tm_unasked_field_tasks_retired';

	private $wpdb;

	private $result = false;

	public function __construct( $args = [] ) {
		global $wpdb;

		$this->wpdb = isset( $args[0] ) && is_object( $args[0] ) ? $args[0] : $wpdb;
	}

	public function run_admin() {
		$wpdb = $this->wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT task_id, payload FROM {$wpdb->prefix}icl_background_task WHERE task_type IN (%s, %s) AND task_status IN (%d, %d, %d)",
				ProcessNewTranslatableFields::class,
				'\\' . ProcessNewTranslatableFields::class,
				BackgroundTask::TASK_STATUS_PENDING,
				BackgroundTask::TASK_STATUS_INPROGRESS,
				BackgroundTask::TASK_STATUS_PAUSED
			),
			'ARRAY_A'
		);

		if ( ! is_array( $rows ) ) {
			return false;
		}

		$ids    = [];
		$fields = [];
		foreach ( $rows as $row ) {
			$ids[]   = (int) $row['task_id'];
			$payload = maybe_unserialize( $row['payload'] );
			foreach ( (array) ( is_array( $payload ) && isset( $payload['newFields'] ) ? $payload['newFields'] : [] ) as $field ) {
				$fields[] = (string) $field;
			}
		}
		$fields = array_values( array_unique( array_filter( $fields, 'strlen' ) ) );

		$this->putToTheRuling( $fields );

		foreach ( $ids as $id ) {
			if ( false === $wpdb->delete( $wpdb->prefix . BackgroundTask::TABLE_NAME, [ 'task_id' => $id ], [ '%d' ] ) ) {
				return false;
			}
		}

		$this->result = [
			'tasks'  => count( $ids ),
			'fields' => $fields,
		];

		if ( $ids ) {
			update_option( self::RESULT_OPTION, $this->result + [ 'at' => gmdate( 'Y-m-d H:i:s' ) ], false );
		}

		return true;
	}

	private function putToTheRuling( array $fields ) {
		if ( ! $fields ) {
			return;
		}

		$stillTranslate = array_map( 'strval', (array) Repository::getCustomFieldsToTranslate() );
		$waiting        = array_map( 'strval', (array) CustomFieldChangeDetector::getDetected() );

		$toRule = array_values( array_diff( array_intersect( $fields, $stillTranslate ), $waiting ) );
		if ( $toRule ) {
			CustomFieldChangeDetector::notify( $toRule );
		}
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
