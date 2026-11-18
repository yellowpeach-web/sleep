<?php

namespace WPML\TM\ATE\JobError;

class UnappliedJobException extends \Exception {

	private $data;

	public function __construct( $message, array $data = [] ) {
		parent::__construct( $message );
		$this->data = $data;
	}

	public function getData() {
		return $this->data;
	}
}
