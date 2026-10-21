<?php

namespace WPML\PB\Shortcode;

class RegistrationPass {

	private $location = 1;

	private $registered = [];

	private $parser;

	public function getParser( callable $createParser ) {
		if ( ! $this->parser ) {
			$this->parser = $createParser();
		}

		return $this->parser;
	}

	public function getLocation() {
		return $this->location;
	}

	public function advanceLocation() {
		++$this->location;
	}

	public function getRegistered( $name ) {
		return isset( $this->registered[ $name ] ) ? $this->registered[ $name ] : null;
	}

	public function setRegistered( $name, array $registration ) {
		$this->registered[ $name ] = $registration;
	}
}
