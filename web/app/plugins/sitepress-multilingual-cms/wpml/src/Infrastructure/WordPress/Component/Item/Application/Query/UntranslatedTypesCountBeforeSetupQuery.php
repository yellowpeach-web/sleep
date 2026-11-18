<?php

namespace WPML\Infrastructure\WordPress\Component\Item\Application\Query;

use WPML\Core\Port\Persistence\Exception\DatabaseErrorException;
use WPML\Core\SharedKernel\Component\Item\Application\Query\Dto\UntranslatedTypeCountDto;
use WPML\Core\SharedKernel\Component\Item\Application\Query\ConfigExcludedPostTypesQueryInterface;
use WPML\Core\SharedKernel\Component\Item\Application\Query\UntranslatedTypesCountQueryInterface;
use WPML\Core\SharedKernel\Component\Post\Application\Query\PublishedPostStatusesQueryInterface;


class UntranslatedTypesCountBeforeSetupQuery implements UntranslatedTypesCountQueryInterface {

  private $postTypesToIgnore = [
    'attachment',
    'wp_template_part',
    'wp_block',
    'nav_menu_item',
    'wp-types-group',
    'wp_global_styles',
  ];

  private $configExcludedPostTypes;

  private $publishedPostStatusesQuery;


  public function __construct(
    ConfigExcludedPostTypesQueryInterface $configExcludedPostTypes,
    PublishedPostStatusesQueryInterface $publishedPostStatusesQuery
  ) {
    $this->configExcludedPostTypes    = $configExcludedPostTypes;
    $this->publishedPostStatusesQuery = $publishedPostStatusesQuery;
  }


  public function forKind() {
    return UntranslatedTypesCountQueryInterface::KIND_POST;
  }


  public function get( array $queryData = [] ): array {
    $wpdb = $GLOBALS['wpdb'];

    $activePostTYpes = get_post_types();
    $searchablePostTypes = array_values( array_diff(
      $activePostTYpes,
      $this->postTypesToIgnore,
      $this->configExcludedPostTypes->get()
    ) );

    $publishedPostStatuses = $this->publishedPostStatusesQuery->get();

    if ( ! $searchablePostTypes || ! $publishedPostStatuses ) {
      return [];
    }

    $postStatusesIn = $this->placeholders( $publishedPostStatuses );
    $postTypesIn    = $this->placeholders( $searchablePostTypes );

    $sql = "
      SELECT
          p.post_type,
          COUNT(DISTINCT p.ID) AS total_items
      FROM {$wpdb->prefix}posts AS p
      WHERE p.post_status IN ({$postStatusesIn})
      AND p.post_type IN ({$postTypesIn})
      GROUP BY p.post_type
      ORDER BY total_items DESC
    ";
    $results = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $publishedPostStatuses, $searchablePostTypes ) ), ARRAY_A );

    $dtos = [];
    foreach ( $results as $result ) {
      $post_type_object = get_post_type_object( $result['post_type'] );
      $dtos[] = new UntranslatedTypeCountDto(
        $post_type_object ? $post_type_object->labels->name : $result['post_type'],
        $post_type_object ? $post_type_object->labels->singular_name : $result['post_type'],
        $result['total_items'],
        UntranslatedTypesCountQueryInterface::KIND_POST,
        $result['post_type']
      );
    }

    return $dtos;
  }


  public function getSomeIds( $numberOfIdsToFetch, $offset, $type ) {
    $wpdb = $GLOBALS['wpdb'];

    $publishedPostStatuses = $this->publishedPostStatusesQuery->get();
    if ( ! $publishedPostStatuses ) {
      return [];
    }

		try {
			$ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT p.ID
					FROM {$wpdb->prefix}posts AS p
					WHERE p.post_type = %s
						AND p.post_status IN (" . implode( ',', array_fill( 0, count( $publishedPostStatuses ), '%s' ) ) . ")
					ORDER BY p.ID ASC
					LIMIT %d OFFSET %d",
					...array_merge( [ $type ], $publishedPostStatuses, [ $numberOfIdsToFetch, $offset ] )
				)
      );
      return $ids;
    } catch ( DatabaseErrorException $e ) {
      return [];
    }
  }


  private function placeholders( array $values ): string {
    return implode( ',', array_fill( 0, count( $values ), '%s' ) );
  }


}
