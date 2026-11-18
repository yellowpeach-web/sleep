<?php

namespace WPML\Media\TextScan;

class Candidates {

	private const ELEMENT_TYPE = 'post_attachment';

	private $sitepress;

	private $wpdb;

	public function __construct( \SitePress $sitepress, \wpdb $wpdb ) {
		$this->sitepress = $sitepress;
		$this->wpdb      = $wpdb;
	}

	public function isOriginal( $id ) {
		$id = (int) $id;
		if ( $id <= 0 ) {
			return false;
		}

		return (int) $this->sitepress->get_original_element_id( $id, self::ELEMENT_TYPE, false, false, true ) === $id;
	}

	public function isImage( $id ) {
		$mime = get_post_mime_type( (int) $id );
		if ( ! is_string( $mime ) || 0 !== strpos( $mime, 'image/' ) ) {
			return false;
		}

		$file = get_attached_file( (int) $id );

		return is_string( $file ) && '' !== $file;
	}

	public function forScan( $scope ) {
		$wpdb = $this->wpdb;

		if ( 'all' === $scope ) {
			return array_map(
				'intval',
				(array) $wpdb->get_col(
					$wpdb->prepare(
						"SELECT p.ID FROM {$wpdb->posts} p"
						. " JOIN {$wpdb->prefix}icl_translations t ON t.element_id = p.ID AND t.element_type = %s AND t.source_language_code IS NULL"
						. " LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s"
						. " WHERE p.post_type = 'attachment' AND p.post_mime_type LIKE %s AND m.meta_id IS NULL"
						. ' ORDER BY p.ID ASC',
						self::ELEMENT_TYPE,
						VerdictStore::STATE_META,
						'image/%'
					)
				)
			);
		}

		$ids = $this->ids( $scope );
		if ( ! $ids ) {
			return [];
		}

		return array_map(
			'intval',
			(array) $wpdb->get_col(
				$wpdb->prepare(
					"SELECT p.ID FROM {$wpdb->posts} p"
					. " JOIN {$wpdb->prefix}icl_translations t ON t.element_id = p.ID AND t.element_type = %s AND t.source_language_code IS NULL"
					. " LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s"
					. " WHERE p.post_type = 'attachment' AND p.post_mime_type LIKE %s AND m.meta_id IS NULL"
					. ' AND p.ID IN (' . implode( ', ', array_fill( 0, count( $ids ), '%d' ) ) . ')'
					. ' ORDER BY p.ID ASC',
					array_merge( [ self::ELEMENT_TYPE, VerdictStore::STATE_META, 'image/%' ], $ids )
				)
			)
		);
	}

	public function countOriginalImages() {
		$wpdb = $this->wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} p"
				. " JOIN {$wpdb->prefix}icl_translations t ON t.element_id = p.ID AND t.element_type = %s AND t.source_language_code IS NULL"
				. " WHERE p.post_type = 'attachment' AND p.post_mime_type LIKE %s",
				self::ELEMENT_TYPE,
				'image/%'
			)
		);
	}

	public function translationsOf( $originalId ) {
		$originalId = (int) $originalId;
		$trid       = $this->sitepress->get_element_trid( $originalId, self::ELEMENT_TYPE );
		if ( ! $trid ) {
			return [];
		}

		$ids = [];
		foreach ( (array) $this->sitepress->get_element_translations( $trid, self::ELEMENT_TYPE ) as $translation ) {
			$elementId = is_object( $translation ) && isset( $translation->element_id ) ? (int) $translation->element_id : 0;
			if ( $elementId > 0 && $elementId !== $originalId && ! in_array( $elementId, $ids, true ) ) {
				$ids[] = $elementId;
			}
		}

		return $ids;
	}

	private function ids( $scope ) {
		$ids = [];
		foreach ( (array) $scope as $id ) {
			$id = (int) $id;
			if ( $id > 0 ) {
				$ids[ $id ] = $id;
			}
		}

		return array_values( $ids );
	}
}
