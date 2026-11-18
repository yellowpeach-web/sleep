<?php

namespace WPML\PB\Cornerstone\Hooks;

use WPML\TM\AutomaticTranslation\Actions\PublishedPostStatuses;
use function WPML\Container\make;

class TranslateEverything implements \IWPML_Frontend_Action, \IWPML_Backend_Action {

	const DOCUMENT_POST_STATUS = 'tco-data';

	public function add_hooks() {
		make( PublishedPostStatuses::class )->add( self::DOCUMENT_POST_STATUS );
	}
}
