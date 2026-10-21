<?php

namespace ACFML\FieldGroup\Endpoints;

use ACFML\FieldGroup\DetectNonTranslatableLocations;
use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use WPML\FP\Cast;
use WPML\FP\Either;
use WPML\FP\Fns;

class DismissTranslateCptModal implements IHandler {

	public function authorize( Collection $data ) {
		return self::canDismiss( (int) $data->get( 'fieldGroupId' ) );
	}

	public function run( Collection $data ) {
		return Either::fromNullable( $data->get( 'fieldGroupId' ) )
			->map( Cast::toInt() )
			->filter( [ self::class, 'canDismiss' ] )
			->map( [ DetectNonTranslatableLocations::class, 'dismiss' ] )
			->map( Fns::always( true ) );
	}

	public static function canDismiss( $fieldGroupId ) {
		return $fieldGroupId > 0
			&& 'acf-field-group' === get_post_type( $fieldGroupId )
			&& current_user_can( 'edit_post', $fieldGroupId );
	}
}
