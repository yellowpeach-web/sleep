<?php

namespace WPML\TM\ATE\TranslateEverything;

use WPML\API\PostTypes;
use WPML\Core\SharedKernel\Component\Post\Domain\TranslateEverythingScope;

class OfferedTypeDefaults {

	public function persist() {
		PostTypesSinceRepositoryFactory::create()
			->fillMissingSinceDates( TranslateEverythingScope::offeredOnly( PostTypes::getAutomaticTranslatable() ) );
	}
}
