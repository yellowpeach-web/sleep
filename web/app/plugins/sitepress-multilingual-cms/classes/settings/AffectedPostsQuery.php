<?php

namespace WPML\TM\Settings;

class AffectedPostsQuery {

	const CARRIER_WALK_PAGE = 1000;

	private $wpdb;

	private $countQuery;

	public function __construct( \wpdb $wpdb, GetNumberOfPostsForCustomField $countQuery ) {
		$this->wpdb       = $wpdb;
		$this->countQuery = $countQuery;
	}

	public function countAffected( array $fieldNames ): int {
		if ( ! $fieldNames ) {
			return 0;
		}

		return (int) $this->countQuery->run(
			wpml_collect( [ 'customFields' => array_values( $fieldNames ) ] ),
			$this->wpdb
		);
	}

	public function countCarriers( array $fieldNames ): int {
		if ( ! $fieldNames ) {
			return 0;
		}
		if ( ! CarrierPosts::isRegistered() ) {
			return $this->countAffected( $fieldNames );
		}

		return count( $this->carrierSet( $fieldNames ) );
	}

	public function countCarriersAnd( array $fieldNames, array $extraPostIds ): int {
		$extraPostIds = array_values( array_unique( array_filter( array_map( 'intval', $extraPostIds ) ) ) );
		if ( ! $fieldNames ) {
			return count( $extraPostIds );
		}
		if ( ! CarrierPosts::isRegistered() ) {
			return $this->countAffected( $fieldNames ) + count( $this->outsideTheSet( $fieldNames, $extraPostIds ) );
		}

		$carriers = $this->carrierSet( $fieldNames );
		foreach ( $extraPostIds as $postId ) {
			$carriers[ $postId ] = true;
		}

		return count( $carriers );
	}

	public function outsideTheSet( array $fieldNames, array $postIds ): array {
		$postIds = array_values( array_unique( array_filter( array_map( 'intval', $postIds ) ) ) );
		if ( ! $postIds || ! $fieldNames ) {
			return $postIds;
		}

		return array_values( array_diff( $postIds, $this->affectedAmong( $fieldNames, $postIds ) ) );
	}

	private function carrierSet( array $fieldNames ): array {
		$carriers   = [];
		$lastPostId = 0;
		do {
			$postIds = $this->page( $fieldNames, $lastPostId, self::CARRIER_WALK_PAGE );
			foreach ( CarrierPosts::of( $postIds ) as $carrierId ) {
				$carriers[ $carrierId ] = true;
			}
			$lastPostId = $postIds ? max( $postIds ) : 0;
		} while ( $postIds );

		return $carriers;
	}

	public function page( array $fieldNames, int $lastPostId, int $limit ): array {
		if ( ! $fieldNames || $limit < 1 ) {
			return [];
		}

		$fieldsIn   = wpml_prepare_in( array_values( $fieldNames ) );
		$lastPostId = (int) $lastPostId;
		$limit      = (int) $limit;

		$ids = $this->wpdb->get_col(
			"SELECT DISTINCT pm.post_id
			FROM {$this->wpdb->postmeta} pm
			INNER JOIN {$this->wpdb->prefix}icl_translations orig
				ON orig.element_id = pm.post_id
				AND orig.element_type LIKE 'post_%'
				AND orig.source_language_code IS NULL
			WHERE pm.meta_key IN ({$fieldsIn})
			  AND pm.meta_key <> ''
			  AND pm.post_id > {$lastPostId}
			  AND EXISTS (
			      SELECT 1 FROM {$this->wpdb->prefix}icl_translations tr
			      WHERE tr.trid = orig.trid
			        AND tr.source_language_code IS NOT NULL
			  )
			ORDER BY pm.post_id ASC
			LIMIT {$limit}"
		);

		return array_map( 'intval', (array) $ids );
	}

	public function affectedAmong( array $fieldNames, array $postIds ): array {
		$postIds = array_values( array_unique( array_filter( array_map( 'intval', $postIds ) ) ) );
		if ( ! $fieldNames || ! $postIds ) {
			return [];
		}

		$wpdb = $this->wpdb;

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT pm.post_id
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->prefix}icl_translations orig
					ON orig.element_id = pm.post_id
					AND orig.element_type LIKE %s
					AND orig.source_language_code IS NULL
				WHERE pm.meta_key IN (" . implode( ', ', array_fill( 0, count( $fieldNames ), '%s' ) ) . ")
				  AND pm.meta_key <> ''
				  AND pm.post_id IN (" . implode( ', ', array_fill( 0, count( $postIds ), '%d' ) ) . ")
				  AND EXISTS (
				      SELECT 1 FROM {$wpdb->prefix}icl_translations tr
				      WHERE tr.trid = orig.trid
				        AND tr.source_language_code IS NOT NULL
				  )",
				...array_merge( [ $wpdb->esc_like( 'post_' ) . '%' ], array_map( 'strval', array_values( $fieldNames ) ), $postIds )
			)
		);

		return array_map( 'intval', (array) $ids );
	}

	public function countTranslated(): int {
		$wpdb = $this->wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT orig.element_id)
				FROM {$wpdb->prefix}icl_translations orig
				WHERE orig.element_type LIKE %s
				  AND orig.source_language_code IS NULL
				  AND EXISTS (
				      SELECT 1 FROM {$wpdb->prefix}icl_translations tr
				      INNER JOIN {$wpdb->prefix}icl_translation_status ts
				          ON ts.translation_id = tr.translation_id
				      WHERE tr.trid = orig.trid
				        AND tr.source_language_code IS NOT NULL
				        AND ts.status = %d
				  )",
				$wpdb->esc_like( 'post_' ) . '%',
				ICL_TM_COMPLETE
			)
		);
	}

	public function pageTranslated( int $lastPostId, int $limit ): array {
		if ( $limit < 1 ) {
			return [];
		}

		$wpdb = $this->wpdb;

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT orig.element_id
				FROM {$wpdb->prefix}icl_translations orig
				WHERE orig.element_type LIKE %s
				  AND orig.source_language_code IS NULL
				  AND orig.element_id > %d
				  AND EXISTS (
				      SELECT 1 FROM {$wpdb->prefix}icl_translations tr
				      INNER JOIN {$wpdb->prefix}icl_translation_status ts
				          ON ts.translation_id = tr.translation_id
				      WHERE tr.trid = orig.trid
				        AND tr.source_language_code IS NOT NULL
				        AND ts.status = %d
				  )
				ORDER BY orig.element_id ASC
				LIMIT %d",
				$wpdb->esc_like( 'post_' ) . '%',
				$lastPostId,
				ICL_TM_COMPLETE,
				$limit
			)
		);

		return array_map( 'intval', (array) $ids );
	}
}
