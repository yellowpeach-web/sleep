<?php

namespace ACFML\Strings\Traversable;

use ACFML\Strings\Transformer\Transformer;

interface Traversable {

	public function traverse( Transformer $transformer, $context = null );
}
