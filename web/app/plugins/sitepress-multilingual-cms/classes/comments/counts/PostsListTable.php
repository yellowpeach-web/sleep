<?php

namespace WPML\Comments\Counts;

class PostsListTable extends \WP_Posts_List_Table {

	private $recording = false;

	private $recorded = array();

	public function display_rows( $posts = array(), $level = 0 ) {
		global $wp_query;

		$column = PostsListColumn::active();
		if ( $column ) {
			if ( $this->hierarchical_display ) {
				$column->prime( $this->rowsCoreRenders( $posts, $level ) );
			} else {
				$column->prime( empty( $posts ) && isset( $wp_query->posts ) ? $wp_query->posts : $posts );
			}
		}

		parent::display_rows( $posts, $level );
	}

	public function single_row( $post, $level = 0 ) {
		if ( $this->recording ) {
			$this->recorded[] = $post;

			return;
		}

		parent::single_row( $post, $level );
	}

	private function rowsCoreRenders( $posts, $level ) {
		$this->recorded  = array();
		$this->recording = true;
		ob_start();
		try {
			parent::display_rows( $posts, $level );
		} finally {
			ob_end_clean();
			$this->recording = false;
		}

		$rows           = $this->recorded;
		$this->recorded = array();

		return $rows;
	}

	public function column_comments( $post ) {
		$column  = PostsListColumn::active();
		$post_id = is_object( $post ) && isset( $post->ID ) ? (int) $post->ID : 0;

		if ( ! $column || ! $column->hasNumbersFor( $post_id ) || ! property_exists( $this, 'comment_pending_count' ) ) {
			parent::column_comments( $post );

			return;
		}

		$pending_counts = $this->comment_pending_count;
		$core_pending   = is_array( $pending_counts ) && isset( $pending_counts[ $post_id ] ) ? (int) $pending_counts[ $post_id ] : 0;
		$pending        = $column->pendingNumber( $post_id, $core_pending );
		if ( $pending !== $core_pending ) {
			$this->comment_pending_count[ $post_id ] = $pending;
		}

		$approved = $column->approvedFilter( $post_id );
		if ( $approved ) {
			add_filter( 'get_comments_number', $approved, PHP_INT_MAX, 2 );
		}

		try {
			parent::column_comments( $post );
		} finally {
			if ( $approved ) {
				remove_filter( 'get_comments_number', $approved, PHP_INT_MAX );
			}
			$this->comment_pending_count = $pending_counts;
		}
	}
}
