<?php

namespace ACFML\Field;

trait IdsListingQueryTrait {

	private function getIdsListing( $listedId, $metaKey, $objectType ) {
		$table    = $this->wpdb->{$objectType . 'meta'};
		$idColumn = $objectType . '_id';

		$ids = $this->wpdb->get_col(
			$this->wpdb->prepare(
				"SELECT {$idColumn} FROM {$table} WHERE meta_key = %s AND ( meta_value = %s OR meta_value LIKE %s OR meta_value LIKE %s )",
				$metaKey,
				(string) $listedId,
				'%' . $this->wpdb->esc_like( '"' . $listedId . '"' ) . '%',
				'%' . $this->wpdb->esc_like( 'i:' . $listedId . ';' ) . '%'
			)
		);

		return array_map( 'intval', $ids );
	}
}
