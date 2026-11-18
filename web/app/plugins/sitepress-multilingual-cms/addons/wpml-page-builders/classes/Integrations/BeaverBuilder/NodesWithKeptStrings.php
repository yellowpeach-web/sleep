<?php

namespace WPML\PB\BeaverBuilder;

use WPML_Beaver_Builder_Translatable_Nodes;
use WPML_Package;
use WPML_PB_String;

class NodesWithKeptStrings implements \IWPML_Page_Builders_Translatable_Nodes {

	private $nodes;

	private $package;

	private $names = [];

	public function __construct( WPML_Beaver_Builder_Translatable_Nodes $nodes ) {
		$this->nodes = $nodes;
	}

	public function setPackage( $package ) {
		$this->package = $package;
		$this->names   = [];
		foreach ( $package ? ( $package->get_package_strings() ?: [] ) : [] as $string ) {
			$this->names[ $string->name ] = true;
		}
	}

	public function get( $node_id, $element ) {
		$strings = $this->nodes->get( $node_id, $element );
		if ( ! $this->names ) {
			return $strings;
		}

		$kept = array_filter(
			$this->nodes->get_inactive( $node_id, $element, $strings ),
			fn( WPML_PB_String $string ) => isset( $this->names[ $this->package->sanitize_string_name( $string->get_name() ) ] )
		);

		return array_merge( $strings, array_values( $kept ) );
	}

	public function update( $node_id, $element, WPML_PB_String $string ) {
		return $this->nodes->update( $node_id, $element, $string );
	}

	public function get_string_name( $node_id, $field, $settings ) {
		return $this->nodes->get_string_name( $node_id, $field, $settings );
	}

	public function initialize_nodes_to_translate() {
		$this->nodes->initialize_nodes_to_translate();
	}
}
