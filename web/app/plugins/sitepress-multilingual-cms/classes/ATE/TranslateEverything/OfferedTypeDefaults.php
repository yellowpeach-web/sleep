<?php

namespace WPML\TM\ATE\TranslateEverything;

use WPML\API\PostTypes;
use WPML\Core\SharedKernel\Component\Post\Domain\TranslateEverythingScope;

class OfferedTypeDefaults {

	public function persist() {
		$offered    = TranslateEverythingScope::offeredOnly( PostTypes::getAutomaticTranslatable() );
		$repository = PostTypesSinceRepositoryFactory::create();

		$repository->fillMissingSinceDates( $offered );
		ParkedTypesOffer::forget( $repository->includeAlwaysAllTypes( $offered ) );
	}
}
