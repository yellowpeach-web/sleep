<?php

namespace WPML\ContentDeletion;

use WPML\LanguageEditor\Save\Phase\SyncSuspension;

class JobsPreCancel implements \IWPML_Backend_Action, \IWPML_Frontend_Action {

	private $answer;

	private $done = array();

	public function __construct( ?DialogAnswer $answer = null ) {
		$this->answer = $answer;
	}

	public function add_hooks() {
		add_action( 'wp_trash_post', array( $this, 'run' ), 2 );
		add_action( 'delete_post', array( $this, 'run' ), 2 );
	}

	public function run( $post_id ) {
		if ( SyncSuspension::isSuspended() || ! $this->answer()->cancelsJobs() ) {
			return;
		}

		$trid = $this->tridOf( $post_id );

		if ( ! $trid || isset( $this->done[ $trid ] ) ) {
			return;
		}

		$this->done[ $trid ] = true;

		$split = SetJobs::collect( $trid );
		$ids   = array_merge( $split['queued'], $split['in_progress'] );

		if ( ! $ids ) {
			return;
		}

		SetJobs::cancel( $ids );

		do_action(
			ItemDeleteRecorder::HOOK_JOBS_CANCELLED,
			count( $split['queued'] ),
			count( $split['in_progress'] )
		);
	}

	private function tridOf( $post_id ) {
		global $wpml_post_translations;

		if ( ! is_object( $wpml_post_translations ) || ! method_exists( $wpml_post_translations, 'get_element_trid' ) ) {
			return 0;
		}

		return (int) $wpml_post_translations->get_element_trid( (int) $post_id );
	}

	private function answer() {
		if ( ! $this->answer ) {
			$this->answer = new DialogAnswer();
		}

		return $this->answer;
	}
}
