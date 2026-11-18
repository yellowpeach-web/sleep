<?php

namespace WPML\TM\AutomaticTranslation\Actions;

class PublishedPostStatuses {

	private $postStatuses = [ 'publish' ];

	public function get() {
		return $this->postStatuses;
	}

	public function add( $postStatus ) {
		if ( ! in_array( $postStatus, $this->postStatuses, true ) ) {
			$this->postStatuses[] = $postStatus;
		}
	}
}
