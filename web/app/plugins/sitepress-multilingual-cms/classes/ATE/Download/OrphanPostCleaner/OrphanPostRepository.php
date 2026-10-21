<?php

namespace WPML\TM\ATE\Download\OrphanPostCleaner;

class OrphanPostRepository {

	private $wpdb;

	public function __construct( \wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function getMaxPostId() {
		$wpdb = $this->wpdb;

		return (int) $wpdb->get_var(
			"SELECT MAX(ID) FROM {$wpdb->posts}"
		);
	}

	public function getOrphanPostIds( $afterId ) {
		$wpdb = $this->wpdb;

		return $wpdb->get_col( $wpdb->prepare(
			"SELECT p.ID FROM {$wpdb->posts} p
			 LEFT JOIN {$wpdb->prefix}icl_translations t
			    ON t.element_id = p.ID AND t.element_type LIKE %s
			 WHERE p.ID > %d AND t.translation_id IS NULL",
			'post_%',
			$afterId
		) );
	}

	public function deletePost( $postId ) {
		wp_delete_post( $postId, true );
	}
}
