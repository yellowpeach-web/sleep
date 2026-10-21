<?php

namespace WPML\Translation;

class AuthorizedOrphanTranslations {

	public static function build( array $candidates, $restrict_to_current_user_readable ) {
		$results = array();

		foreach ( $candidates as $candidate ) {
			$candidate_id = (int) $candidate->element_id;

			if (
				$restrict_to_current_user_readable
				&& ! current_user_can( 'read_post', $candidate_id )
				&& ! current_user_can( 'edit_post', $candidate_id )
			) {
				continue;
			}

			$post = get_post( $candidate_id );
			if ( ! $post ) {
				continue;
			}

			$title = (string) $post->post_title;
			if ( '' === $title ) {
				$title = mb_substr( (string) $post->post_content, 0, 30 ) . '...';
			}

			$results[] = (object) array(
				'value' => (string) $candidate->trid,
				'label' => '[' . $candidate->language_code . '] ' . $title,
			);
		}

		return $results;
	}
}
