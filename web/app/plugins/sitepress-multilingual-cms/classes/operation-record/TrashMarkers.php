<?php

namespace WPML\OperationRecord;

class TrashMarkers {

	const META_KEY = '_wpml_operation_record';

	const POSTS_LIMIT = 500;

	public function mark( $post_id, $record_id ) {
		update_post_meta( (int) $post_id, self::META_KEY, (int) $record_id );
	}

	public function recordOf( $post_id ) {
		return (int) get_post_meta( (int) $post_id, self::META_KEY, true );
	}

	public function postsOf( $record_id, $after = 0 ) {
		global $wpdb;

		$record_id = (int) $record_id;
		$after     = max( (int) $after, 0 );

		if ( $record_id <= 0 ) {
			return array();
		}

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT post_id
				   FROM {$wpdb->postmeta}
				  WHERE meta_key = %s
				    AND meta_value = %s
				    AND post_id > %d
			   ORDER BY post_id ASC
				  LIMIT %d",
				self::META_KEY,
				(string) $record_id,
				$after,
				self::POSTS_LIMIT
			)
		);

		return array_map( 'intval', (array) $ids );
	}

	public function restorableCount( $record_id, $after = 0 ) {
		global $wpdb;

		$record_id = (int) $record_id;
		$after     = max( (int) $after, 0 );

		if ( $record_id <= 0 ) {
			return 0;
		}

		$total = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				   FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				  WHERE pm.meta_key = %s
				    AND pm.meta_value = %s
				    AND pm.post_id > %d
				    AND p.post_status = 'trash'",
				self::META_KEY,
				(string) $record_id,
				$after
			)
		);

		return (int) $total;
	}

	public function clear( $post_id ) {
		delete_post_meta( (int) $post_id, self::META_KEY );
	}
}
