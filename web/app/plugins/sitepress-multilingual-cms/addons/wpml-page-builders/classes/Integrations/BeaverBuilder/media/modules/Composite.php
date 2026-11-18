<?php

namespace WPML\PB\BeaverBuilder\Media\Modules;

class Composite extends \WPML_Beaver_Builder_Media_Node {

	private $nodes;

	public function __construct( \IWPML_PB_Media_Find_And_Translate $media_translate, array $nodes ) {
		parent::__construct( $media_translate );
		$this->nodes = $nodes;
	}

	public function translate( $node_data, $target_lang, $source_lang ) {
		foreach ( $this->nodes as $node ) {
			$node_data = $node->translate( $node_data, $target_lang, $source_lang );
		}

		return $node_data;
	}
}
