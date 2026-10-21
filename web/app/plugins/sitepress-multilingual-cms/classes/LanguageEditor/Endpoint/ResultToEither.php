<?php

namespace WPML\LanguageEditor\Endpoint;

use WPML\Core\Component\LanguageEditor\Application\Result;
use WPML\FP\Either;

trait ResultToEither {

	private function toEither( Result $result ) {
		return $result->isOk()
			? Either::right( $result->payload() )
			: Either::left( $result->payload() );
	}
}
