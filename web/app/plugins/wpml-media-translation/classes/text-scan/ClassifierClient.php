<?php

namespace WPML\Media\TextScan;

interface ClassifierClient {

	public function classify( array $items, $purpose, $timeout );
}
