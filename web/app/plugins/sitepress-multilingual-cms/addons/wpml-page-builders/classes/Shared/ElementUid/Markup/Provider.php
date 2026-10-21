<?php

namespace WPML\PB\ElementUid\Markup;

interface Provider {

	public function appliesTo( $postId );

	public function getElements( $postId );

	public function getSource();
}
