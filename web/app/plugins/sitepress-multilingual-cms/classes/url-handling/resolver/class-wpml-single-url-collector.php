<?php

use WPML\Core\Component\Translation\Domain\Links\CollectorInterface;

class WPML_Single_Url_Collector implements CollectorInterface {

	private $post_id;

	private $term_id;

	public function getItemsLinkedInContent( string $content ) {
		return [];
	}

	public function addItemByIdAndType( int $id, string $type ) {
		if ( 'post' === $type && null === $this->post_id ) {
			$this->post_id = $id;
		}

		if ( 'term' === $type && null === $this->term_id ) {
			$this->term_id = $id;
		}
	}

	public function get_post_id() {
		return $this->post_id;
	}

	public function get_term_id() {
		return $this->term_id;
	}
}
