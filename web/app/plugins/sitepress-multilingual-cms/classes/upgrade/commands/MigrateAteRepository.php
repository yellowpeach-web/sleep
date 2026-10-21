<?php

namespace WPML\TM\Upgrade\Commands;

class MigrateAteRepository implements \IWPML_Upgrade_Command {

	const TABLE_NAME            = 'icl_translate_job';
	const COLUMN_EDITOR_JOB_ID  = 'editor_job_id';
	const COLUMN_EDIT_TIMESTAMP = 'edit_timestamp';

	const OPTION_NAME_REPO = 'WPML_TM_ATE_JOBS';

	private $schema;

	private $result = false;

	public function __construct( array $args ) {
		$this->schema = $args[0];
	}

	public function run() {
		$this->result = $this->addColumnsToJobsTable();

		if ( $this->result ) {
			$this->migrateOldRepository();
		}

		return $this->result;
	}

	private function addColumnsToJobsTable() {
		$result = true;

		if ( ! $this->schema->does_column_exist( self::TABLE_NAME, self::COLUMN_EDITOR_JOB_ID ) ) {
			$result = $this->schema->add_column( self::TABLE_NAME, self::COLUMN_EDITOR_JOB_ID, 'bigint(20) unsigned NULL' );
		}

		return $result;
	}

	private function migrateOldRepository() {
		$records = get_option( self::OPTION_NAME_REPO );

		if ( is_array( $records ) && $records ) {
			$wpdb   = $this->schema->get_wpdb();
			$jobIds = array_map( 'intval', array_keys( $records ) );
			$cases  = $this->getAteJobIdCaseArgs( $records );

			if ( ! $cases ) {
				$wpdb->query(
					$wpdb->prepare(
						"UPDATE {$wpdb->prefix}icl_translate_job
						SET editor_job_id = 0
						WHERE editor_job_id IS NULL
							AND job_id IN (" . implode( ', ', array_fill( 0, count( $jobIds ), '%d' ) ) . ')',
						$jobIds
					)
				);
			} else {
				$wpdb->query(
					$wpdb->prepare(
						"UPDATE {$wpdb->prefix}icl_translate_job
						SET editor_job_id = (
							CASE job_id
								" . implode( ' ', array_fill( 0, count( $cases ) / 2, 'WHEN %d THEN %d' ) ) . '
								ELSE 0
							END
						)
						WHERE editor_job_id IS NULL
							AND job_id IN (' . implode( ', ', array_fill( 0, count( $jobIds ), '%d' ) ) . ')',
						array_merge( $cases, $jobIds )
					)
				);
			}
		}

		$this->disableAutoloadOnOldOption();
	}

	private function getAteJobIdCaseArgs( array $records ) {
		$args = [];
		foreach ( $records as $jobId => $data ) {
			if ( isset( $data['ate_job_id'] ) ) {
				$args[] = (int) $jobId;
				$args[] = (int) $data['ate_job_id'];
			}
		}

		return $args;
	}

	private function disableAutoloadOnOldOption() {
		$wpdb = $this->schema->get_wpdb();

		$wpdb->update(
			$wpdb->options,
			[ 'autoload' => 'no' ],
			[ 'option_name' => self::OPTION_NAME_REPO ]
		);
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
