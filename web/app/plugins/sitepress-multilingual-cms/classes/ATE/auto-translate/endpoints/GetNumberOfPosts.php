<?php

namespace WPML\TM\ATE\AutoTranslate\Endpoint;

use WPML\API\PostTypes;
use WPML\Collect\Support\Collection;

class GetNumberOfPosts {

	public function run( Collection $data, \wpdb $wpdb ) {
		$postTypes = array_values( (array) $data->get( 'postTypes', PostTypes::getAutomaticTranslatable() ) );
		if ( ! $postTypes ) {
			return 0;
		}

		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(id) FROM {$wpdb->posts} WHERE post_type IN (" . implode( ', ', array_fill( 0, count( $postTypes ), '%s' ) ) . ") AND post_status='publish'",
				$postTypes
			)
		);
	}
}
