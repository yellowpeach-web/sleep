<?php

namespace WPML\PB\ElementUid;

class TimestampsFilter {

	private $registry;

	public function __construct( Registry $registry ) {
		$this->registry = $registry;
	}

	public function get( $timestamps, $postId ) {
		$timestamps = is_array( $timestamps ) ? $timestamps : [];

		return array_replace( $timestamps, $this->registry->getTimestamps( $postId ) );
	}
}
