<?php

namespace WPML\Upgrade\Commands;

use WPML\TM\Jobs\JobLog;

class ReleaseAdoptedAteJobBindings implements \IWPML_Upgrade_Command {

	const POSITION_OPTION = 'wpml_release_adopted_ate_bindings_position';

	const WINDOW_SIZE = 20000;

	private $schema;

	private $result = false;

	public function __construct( array $args ) {
		$this->schema = $args[0];
	}

	public static function resetPosition() {
		delete_option( self::POSITION_OPTION );
	}

	public function run() {
		$wpdb = $this->schema->get_wpdb();

		$position = (int) get_option( self::POSITION_OPTION, 0 );
		$lastId = (int) $wpdb->get_var( "SELECT MAX(job_id) FROM {$wpdb->prefix}icl_translate_job" );

		if ( $position >= $lastId ) {
			self::resetPosition();
			$this->result = true;

			return true;
		}

		$windowEnd = $this->windowEnd( $wpdb, $position, $lastId );

		$this->releaseWindow( $wpdb, $position, $windowEnd );

		$this->result = $windowEnd >= $lastId;

		if ( $this->result ) {
			self::resetPosition();
		} else {
			update_option( self::POSITION_OPTION, $windowEnd, false );
		}

		return $this->result;
	}

	private function releaseWindow( \wpdb $wpdb, $afterId, $throughId ) {
		$prefix = $wpdb->prefix;

		$impostors = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT impostor.job_id, impostor.rid, impostor.editor_job_id, owner.job_id AS owner_job_id
				   FROM {$prefix}icl_translate_job impostor
				  INNER JOIN {$prefix}icl_translate_job owner
				     ON owner.editor_job_id = impostor.editor_job_id
				    AND owner.job_id = impostor.rid
				    AND owner.job_id <> impostor.job_id
				  WHERE impostor.job_id > %d
				    AND impostor.job_id <= %d
				    AND impostor.editor_job_id IS NOT NULL
				    AND impostor.editor_job_id > 0",
				$afterId,
				$throughId
			)
		);

		foreach ( (array) $impostors as $row ) {
			$wpdb->update(
				$prefix . 'icl_translate_job',
				[ 'editor_job_id' => null ],
				[ 'job_id' => (int) $row->job_id ],
				[ '%s' ],
				[ '%d' ]
			);

			if ( class_exists( JobLog::class ) ) {
				JobLog::add(
					'ate_binding_released_adopted_record',
					[
						'job_id'        => (int) $row->job_id,
						'rid'           => (int) $row->rid,
						'ate_job_id'    => (int) $row->editor_job_id,
						'held_by'       => (int) $row->owner_job_id,
					]
				);
			}
		}
	}

	private function windowEnd( \wpdb $wpdb, $afterId, $lastId ) {
		$pageEnd = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(job_id) FROM ( SELECT job_id FROM {$wpdb->prefix}icl_translate_job WHERE job_id > %d ORDER BY job_id LIMIT %d ) AS page",
				$afterId,
				self::WINDOW_SIZE
			)
		);

		return $pageEnd > 0 ? min( $pageEnd, $lastId ) : $lastId;
	}

	public function run_admin() {
		return $this->run();
	}

	public function run_ajax() {
		return null;
	}

	public function run_frontend() {
		return $this->run();
	}

	public function get_results() {
		return $this->result;
	}
}
