<?php

namespace WPML\TranslationMode\Endpoint;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use WPML\FP\Right;
use WPML\Setup\Option;

class SetReviewMode implements IHandler {

	public function run( Collection $data ) {
		Option::setReviewMode( $data->get( 'reviewMode' ) );

		return Right::of( true );
	}
}
