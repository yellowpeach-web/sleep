<?php

namespace ACFML\Strings\Transformer;

use ACFML\Strings\Package;

class Translate implements Transformer {

	private $package;

	public function __construct( Package $package ) {
		$this->package = $package;
	}

	public function transform( $value, $stringData ) {
		return $this->package->translate( $value, $stringData );
	}
}
