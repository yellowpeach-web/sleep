<?php

namespace WPML\StringTranslation\Infrastructure\StringCore\Query;

use WPML\StringTranslation\Application\StringCore\Query\Criteria\SearchCriteria;
use WPML\StringTranslation\Application\StringCore\Query\FindAllStringsCountQueryInterface;

class FindAllStringsCountQuery implements FindAllStringsCountQueryInterface {

	private $queryBuilder;

	public function __construct(
		FindAllStringsCountQueryBuilder $queryBuilder
	) {
		$this->queryBuilder = $queryBuilder;
	}

	public function execute( SearchCriteria $criteria ): int {
		global $wpdb;

		$query = $this->queryBuilder->build( $criteria );
		$count = $wpdb->get_var( $query );

		if ( null === $count ) {
			FailedStatement::throwIfFailed( $wpdb, 'count the strings' );

			return 0;
		}

		return (int) $count;
	}
}
