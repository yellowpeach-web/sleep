<?php

use WPML\FP\Obj;
use \WPML\LIB\WP\Post;

class WPML_TP_Sync_Update_Job {

	private $strategies = array(
		WPML_TM_Job_Entity::POST_TYPE    => 'update_post_job',
		WPML_TM_Job_Entity::PACKAGE_TYPE => 'update_post_job',
		WPML_TM_Job_Entity::STRING_BATCH => 'update_post_job',
	);

	private $wpdb;

	private $sitepress;

	public function __construct( wpdb $wpdb, SitePress $sitepress ) {
		$this->wpdb      = $wpdb;
		$this->sitepress = $sitepress;
	}

	public function cancel( WPML_TM_Job_Entity $job, $untranslatedStatus = ICL_TM_NOT_TRANSLATED ) {
		if ( ! array_key_exists( $job->get_type(), $this->strategies ) ) {
			return $job;
		}

		$wpdb = $this->wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT status, translation_id FROM {$wpdb->prefix}icl_translation_status WHERE rid = %d",
				$job->get_id()
			)
		);

		if ( ! $row ) {
			return $job;
		}

		$freshStatus = (int) $row->status;

		if ( in_array( $freshStatus, [ ICL_TM_COMPLETE, ICL_TM_NOT_TRANSLATED, ICL_TM_ATE_CANCELLED ], true ) ) {
			$job->set_status( $freshStatus );

			return $job;
		}

		try {
			$status = \WPML\Translation\PreviousStateServiceFactory::create()->revertToPreviousState(
				(int) $row->translation_id,
				(int) $untranslatedStatus
			);
		} catch ( \WPML\PHP\Exception\InvalidItemIdException $e ) {
			return $job;
		}

		$job->set_status( $status );

		if ( ICL_TM_NOT_TRANSLATED === $status && WPML_TM_Job_Entity::POST_TYPE === $job->get_type() ) {
			$this->delete_orphan_translation( $job );
		}

		return $job;
	}

	public function update_state( WPML_TM_Job_Entity $job ) {
		if ( ! array_key_exists( $job->get_type(), $this->strategies ) ) {
			return $job;
		}

		$method = $this->strategies[ $job->get_type() ];

		return call_user_func( array( $this, $method ), $job );
	}


	private function update_post_job( WPML_TM_Job_Entity $job ) {
		$rid = $job->get_id();

		if ( $job->get_status() === ICL_TM_NOT_TRANSLATED ) {
			$prev_status = \WPML\Translation\PreviousStateServiceFactory::create()->getByRid( $rid );
      if ( $prev_status && Obj::prop( 'needs_update', $prev_status ) ) {
				$prev_status['_prevstate'] = null;
				$this->wpdb->update( $this->wpdb->prefix . 'icl_translation_status', $prev_status, [ 'rid' => $rid ] );
				$job->set_needs_update( true );
				return $job;
			}
		}

		$this->wpdb->update(
			$this->wpdb->prefix . 'icl_translation_status',
			array(
				'status'      => $job->get_status(),
				'tp_revision' => $job->get_revision(),
				'ts_status'   => $this->get_ts_status_in_ts_format( $job ),
			),
			array( 'rid' => $rid )
		);

		if ( ICL_TM_NOT_TRANSLATED === $job->get_status() ) {
			$this->delete_orphan_translation( $job );
		}

		return $job;
	}

	private function delete_orphan_translation( WPML_TM_Job_Entity $job ): void {
		$post_type = Post::getType( $job->get_original_element_id() );

		if ( $post_type ) {
			$this->sitepress->delete_orphan_element(
				$job->get_original_element_id(),
				'post_' . $post_type,
				$job->get_target_language()
			);
		}
	}


	private function get_ts_status_in_ts_format( WPML_TM_Job_Entity $job ) {
		$ts_status = $job->get_ts_status();

		return $ts_status ? wp_json_encode( array( 'ts_status' => json_decode( (string) $ts_status ) ) ) : null;
	}
}
