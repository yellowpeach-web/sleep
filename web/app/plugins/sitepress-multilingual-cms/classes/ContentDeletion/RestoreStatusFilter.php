<?php

namespace WPML\ContentDeletion;

use WPML\OperationRecord\TrashMarkers;

class RestoreStatusFilter implements \IWPML_Backend_Action {

	private $answer;

	private $markers;

	public function add_hooks() {
		add_filter( 'wp_untrash_post_status', array( $this, 'restoreOwnStatus' ), 10, 3 );
	}

	public function shouldArm() {
		if ( ! $this->answer ) {
			$this->answer = new RestoreAnswer();
		}

		return null !== $this->answer->scope();
	}

	public function isWpmlTrashed( $post_id ) {
		if ( ! $this->markers ) {
			$this->markers = new TrashMarkers();
		}

		return $this->markers->recordOf( (int) $post_id ) > 0;
	}

	public function restoreOwnStatus( $status, $post_id, $previous_status ) {
		if ( ! $this->shouldArm() && ! $this->isWpmlTrashed( $post_id ) ) {
			return $status;
		}

		return is_string( $previous_status ) && '' !== $previous_status ? $previous_status : $status;
	}
}
