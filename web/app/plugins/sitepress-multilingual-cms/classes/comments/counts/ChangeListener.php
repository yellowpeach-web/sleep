<?php

namespace WPML\Comments\Counts;

class ChangeListener {

	private $store;

	private $scheduler;

	private $exclusions;

	private $moved = array();

	private $counters;

	public function __construct( Store $store, ?Scheduler $scheduler = null, ?Exclusions $exclusions = null, ?Counters $counters = null ) {
		$this->store      = $store;
		$this->scheduler  = $scheduler;
		$this->exclusions = $exclusions;
		$this->counters   = $counters;
	}

	public function mark() {
		$this->store->markOutOfDate();
	}

	public function commentDataUpdated( $data, $comment ) {
		if (
			is_array( $data )
			&& is_array( $comment )
			&& (
				(int) $this->field( $data, 'comment_post_ID' ) !== (int) $this->field( $comment, 'comment_post_ID' )
				|| (string) $this->field( $data, 'comment_type' ) !== (string) $this->field( $comment, 'comment_type' )
			)
		) {
			$comment_id = (int) $this->field( $data, 'comment_ID' );
			if ( $comment_id > 0 ) {
				$this->moved[ $comment_id ] = true;
			} else {
				$this->store->markOutOfDate();
			}
		}

		return $data;
	}

	public function commentEdited( $comment_id ) {
		$comment_id = (int) $comment_id;
		if ( ! isset( $this->moved[ $comment_id ] ) ) {
			return;
		}
		unset( $this->moved[ $comment_id ] );

		$this->store->markOutOfDate();
	}

	public function translationUpdated( $args ) {
		if ( is_array( $args ) && array( 'trid' ) === array_keys( $args ) ) {
			return;
		}

		$type    = is_array( $args ) && isset( $args['element_type'] ) ? $args['element_type'] : '';
		$post_id = is_array( $args ) && isset( $args['element_id'] ) ? (int) $args['element_id'] : 0;

		if ( empty( $type ) ) {
			if ( $post_id > 0 && isset( $args['context'] ) && 'post' === $args['context'] ) {
				$this->markPostRow( $post_id );

				return;
			}

			$this->store->markOutsideChange();

			return;
		}

		if ( 'comment' === $type ) {
			$this->store->markOutsideChange();

			return;
		}

		if (
			! is_string( $type )
			|| 0 !== strpos( $type, 'post_' )
			|| in_array( substr( $type, 5 ), $this->exclusions()->hiddenPostTypes(), true )
		) {
			return;
		}

		$this->markPostRow( $post_id );
	}

	private function markPostRow( $post_id ) {
		if (
			$post_id > 0
			&& ! ( $this->counters && $this->counters->hasPendingChanges() )
			&& ! $this->store->postHasComments( $post_id )
		) {
			return;
		}

		$this->store->markOutsideChange();
	}

	public function pluginSwitched() {
		$this->store->markOutsideChange();

		if ( $this->scheduler ) {
			$this->scheduler->cancelLateRecount();
		}
	}

	private function exclusions() {
		if ( ! $this->exclusions ) {
			$this->exclusions = new Exclusions();
		}

		return $this->exclusions;
	}

	private function field( array $row, $key ) {
		return isset( $row[ $key ] ) ? $row[ $key ] : '';
	}
}
