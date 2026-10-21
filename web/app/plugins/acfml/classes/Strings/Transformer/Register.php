<?php

namespace ACFML\Strings\Transformer;

use ACFML\Strings\Package;

class Register implements Transformer {

	private $package;

	public function __construct( Package $package ) {
		$this->package = $package;
	}

	public function start() {
		$this->package->recordRegisteredStrings();
	}

	public function end() {
		$this->package->cleanupUnusedStrings();
	}

	public function transform( $value, $stringData ) {
		$this->package->register( $value, $stringData );

		return $value;
	}
}
