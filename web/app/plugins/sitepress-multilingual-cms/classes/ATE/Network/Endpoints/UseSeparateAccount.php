<?php

namespace WPML\TM\ATE\Network\Endpoints;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use WPML\FP\Either;
use WPML\TM\ATE\AutoTranslate\Endpoint\EnableATE;
use WPML\TM\ATE\Network\SubsiteAttachState;
use function WPML\Container\make;

class UseSeparateAccount implements IHandler {

	public function run( Collection $data ) {
		if ( ! SubsiteAttachState::canUseSeparateAccount() ) {
			/* translators: Answer to a request for a separate automatic translation account made before WPML offers one; "the main site" is the WordPress network's main site. */
			return Either::left( __( 'This subsite is still connecting to the main site\'s account.', 'sitepress' ) );
		}

		SubsiteAttachState::chooseSeparate();

		return make( EnableATE::class )->enable();
	}
}
