<?php

namespace WPML\PB\Links;

interface DataStorage {

	public function get( $postId, $metaKey );

	public function save( $postId, $metaKey, $data );
}
