<?php

namespace WPML\WPSEO\YoastSEO\Indexable;

use WPML\LIB\WP\WPDB;
use Yoast\WP\Lib\ORM;
use Yoast\WP\SEO\Actions\Indexing\Post_Link_Indexing_Action;
use Yoast\WP\SEO\Main;
use Yoast\WP\SEO\Models\Indexable;
use Yoast\WP\SEO\Models\SEO_Links;
use Yoast\WP\SEO\Repositories\Indexable_Repository;
use Yoast\WP\SEO\Repositories\SEO_Links_Repository;
use Yoast\WP\SEO\Surfaces\Classes_Surface;

class SiteLinksStoredAsExternalRepair {

	const EXTERNAL_LINK_TYPES = [ SEO_Links::TYPE_EXTERNAL, SEO_Links::TYPE_EXTERNAL_IMAGE ];

	const YOAST_UNINDEXED_POST_LINKS_TRANSIENTS = [
		Post_Link_Indexing_Action::UNINDEXED_COUNT_TRANSIENT,
		Post_Link_Indexing_Action::UNINDEXED_LIMITED_COUNT_TRANSIENT,
	];

	const INDEXABLES_PER_BATCH = 500;

	private $wpdb;

	private $sitepress;

	public function __construct( \wpdb $wpdb, \SitePress $sitepress ) {
		$this->wpdb      = $wpdb;
		$this->sitepress = $sitepress;
	}

	public function queueRebuild(): void {
		if ( WPML_LANGUAGE_NEGOTIATION_TYPE_DIRECTORY !== (int) $this->sitepress->get_setting( 'language_negotiation_type' ) ) {
			return;
		}

		if ( ! class_exists( SEO_Links_Repository::class ) || ! class_exists( Indexable_Repository::class ) ) {
			return;
		}

		$yoastSEO = YoastSEO();

		$classes = $yoastSEO->classes;

		$linksRepository = $classes->get( SEO_Links_Repository::class );

		$indexableRepository = $classes->get( Indexable_Repository::class );

		WPDB::withoutError(
			function () use ( $linksRepository, $indexableRepository ) {
				$indexableIds = $this->getIndexablesWithSiteLinksStoredAsExternal( $linksRepository );

				foreach ( array_chunk( $indexableIds, self::INDEXABLES_PER_BATCH ) as $indexableIdsBatch ) {
					$this->queueLinksRebuildOfPosts( $indexableIdsBatch, $linksRepository, $indexableRepository );
				}

				if ( $indexableIds ) {
					foreach ( self::YOAST_UNINDEXED_POST_LINKS_TRANSIENTS as $transient ) {
						delete_transient( $transient );
					}
				}
			}
		);
	}

	private function getIndexablesWithSiteLinksStoredAsExternal( SEO_Links_Repository $linksRepository ): array {
		$query = $linksRepository->query();

		$rows = $query
			->select( 'indexable_id' )
			->distinct()
			->where_in( 'type', self::EXTERNAL_LINK_TYPES )
			->where_like( 'url', '%//' . $this->wpdb->esc_like( $this->getSiteAddressWithoutScheme() ) . '/%' )
			->where_not_null( 'indexable_id' )
			->find_array();

		return array_map( 'intval', array_column( (array) $rows, 'indexable_id' ) );
	}

	private function queueLinksRebuildOfPosts( array $indexableIds, SEO_Links_Repository $linksRepository, Indexable_Repository $indexableRepository ): void {
		$posts = array_values(
			array_filter(
				$indexableRepository->find_by_ids( $indexableIds ),
				function ( $indexable ) {
					return 'post' === $indexable->object_type;
				}
			)
		);

		if ( ! $posts ) {
			return;
		}

		$targetIndexableIds = $this->getTargetIndexables( $this->getIds( $posts ), $linksRepository );

		foreach ( $posts as $post ) {
			$linksRepository->delete_all_by_indexable_id( $post->id );

			$post->link_count = null;
			$post->save();
		}

		if ( $targetIndexableIds ) {
			$this->recountIncomingLinks( $targetIndexableIds, $linksRepository, $indexableRepository );
		}
	}

	private function getTargetIndexables( array $indexableIds, SEO_Links_Repository $linksRepository ): array {
		$query = $linksRepository->query();

		$rows = $query
			->select( 'target_indexable_id' )
			->distinct()
			->where_in( 'indexable_id', $indexableIds )
			->where_not_null( 'target_indexable_id' )
			->find_array();

		return array_map( 'intval', array_column( (array) $rows, 'target_indexable_id' ) );
	}

	private function recountIncomingLinks( array $indexableIds, SEO_Links_Repository $linksRepository, Indexable_Repository $indexableRepository ): void {
		$counts = array_column(
			$linksRepository->get_incoming_link_counts_for_indexable_ids( $indexableIds ),
			'incoming',
			'target_indexable_id'
		);

		$indexables = $indexableRepository->find_by_ids( $indexableIds );

		foreach ( $indexables as $indexable ) {
			if ( isset( $counts[ $indexable->id ] ) ) {
				$indexable->incoming_link_count = (int) $counts[ $indexable->id ];
				$indexable->save();
			}
		}
	}

	private function getIds( array $indexables ): array {
		return array_map(
			function ( $indexable ) {
				return (int) $indexable->id;
			},
			$indexables
		);
	}

	private function getSiteAddressWithoutScheme(): string {
		return untrailingslashit( (string) preg_replace( '#^https?://#i', '', (string) get_option( 'home' ) ) );
	}
}
