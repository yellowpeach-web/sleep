<?php

namespace WPML\StringTranslation\Infrastructure\StringPackage\Query;

use WPML\StringTranslation\Application\StringPackage\Query\Criteria\SearchPopulatedKindsCriteria;
use WPML\StringTranslation\Application\StringPackage\Query\SearchPopulatedKindsQueryInterface;

class SearchPopulatedKindsQuery implements SearchPopulatedKindsQueryInterface {

	private $wpdb;

	private $sitepress;

	private $queryBuilder;

	public function __construct(
		$wpdb,
		$sitepress,
		SearchPopulatedKindsQueryBuilder $queryBuilder
	) {
		$this->wpdb = $wpdb;
		$this->sitepress = $sitepress;
		$this->queryBuilder = $queryBuilder;
	}

	public function get( SearchPopulatedKindsCriteria $criteria ) {
		$populatedKinds = $criteria->getStringPackageTypeIds();
		foreach( $populatedKinds as $postTypeIndex => $postType ) {
			$query = $this->queryBuilder->build( $criteria, $postType );
			if ( ! $this->wpdb->get_col( $query ) ) {
				unset( $populatedKinds[ $postTypeIndex ] );
			}
		}

		return $populatedKinds;
	}
}
