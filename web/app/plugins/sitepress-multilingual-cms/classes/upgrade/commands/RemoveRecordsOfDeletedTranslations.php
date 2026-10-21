<?php

namespace WPML\Upgrade\Commands;

use WPML\TM\Jobs\JobLog;

class RemoveRecordsOfDeletedTranslations implements \IWPML_Upgrade_Command {

	const BATCH_SIZE = 500;

	private $schema;

	private $result = false;

	public function __construct( array $args ) {
		$this->schema = $args[0];
	}

	public function run() {
		$wpdb      = $this->schema->get_wpdb();
		$batchSize = $this->batchSize();

		$translationIds = $this->findDeletedTranslationRecords( $wpdb, $batchSize );

		if ( $translationIds ) {
			\WPML_Translation_Records_Delete::translations_by_ids( $translationIds );

			JobLog::add(
				'upgrade_removed_records_of_deleted_translations',
				[ 'count' => count( $translationIds ) ]
			);

			$this->result = false;

			return false;
		}

		$unlinked = \WPML_Unlinked_Translation_Records::sweep_batch( $wpdb, $batchSize );

		if ( $unlinked ) {
			JobLog::add(
				'upgrade_removed_unlinked_translation_records',
				[ 'count' => $unlinked ]
			);

			$this->result = false;

			return false;
		}

		$this->result = true;

		return true;
	}

	private function batchSize() {
		$size = (int) apply_filters( 'wpml_upgrade_deleted_translation_records_batch_size', self::BATCH_SIZE );

		return max( 1, $size );
	}

	private function findDeletedTranslationRecords( \wpdb $wpdb, $batchSize ) {
		$sql = "SELECT DISTINCT t.translation_id
			FROM {$wpdb->prefix}icl_translations t
			INNER JOIN {$wpdb->prefix}icl_translation_status ts
			        ON ts.translation_id = t.translation_id
			WHERE t.source_language_code IS NOT NULL
			  AND t.element_type LIKE 'post\\_%%'
			  AND ts.status = %d
			  AND NOT EXISTS (
			      SELECT 1 FROM {$wpdb->posts} p WHERE p.ID = t.element_id
			  )
			ORDER BY t.translation_id
			LIMIT %d";

		$ids = $wpdb->get_col(
			$wpdb->prepare( $sql, ICL_TM_COMPLETE, $batchSize )
		);

		return array_values( array_filter( array_map( 'intval', (array) $ids ) ) );
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
