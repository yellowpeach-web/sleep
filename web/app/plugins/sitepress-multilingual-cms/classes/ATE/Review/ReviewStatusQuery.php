<?php

namespace WPML\TM\ATE\Review;

class ReviewStatusQuery {

	private $wpdb;

	public function __construct( \wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function getForPost( $postId, $postType, $language ) {
		$wpdb = $this->wpdb;
		$row  = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT s.review_status, t.language_code, t.source_language_code
					FROM {$wpdb->prefix}icl_translations t
					INNER JOIN {$wpdb->prefix}icl_translation_status s
						ON s.translation_id = t.translation_id
					INNER JOIN {$wpdb->prefix}icl_translate_job j
						ON j.rid = s.rid
					INNER JOIN {$wpdb->prefix}icl_translate ic
						ON ic.job_id = j.job_id AND ic.field_type = 'original_id'
					INNER JOIN {$wpdb->prefix}icl_translations ito
						ON ito.element_id = ic.field_data AND ito.trid = t.trid
					WHERE t.element_id = %d AND t.element_type = %s AND t.language_code = %s
					ORDER BY j.job_id DESC
					LIMIT 1",
				(int) $postId,
				'post_' . $postType,
				$language
			)
		);

		return $row instanceof \stdClass ? $row : null;
	}
}
