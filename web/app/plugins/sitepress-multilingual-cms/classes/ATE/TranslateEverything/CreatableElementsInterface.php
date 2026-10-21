<?php

namespace WPML\TM\ATE\TranslateEverything;

interface CreatableElementsInterface {

	public function filterCreatableElements( array $elements ): array;
}
