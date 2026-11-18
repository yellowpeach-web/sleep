<?php

namespace WPML\PB\SiteOrigin;

class PositionStringNames {

	private $nodes;

	public function __construct( \IWPML_Page_Builders_Translatable_Nodes $nodes ) {
		$this->nodes = $nodes;
	}

	public function get( $nodeId, array $settings ) {
		unset( $settings[ TranslatableNodes::SETTINGS_FIELD ][ TranslatableNodes::WIDGET_ID_FIELD ] );

		return array_map(
			function ( \WPML_PB_String $pbString ) {
				return $pbString->get_name();
			},
			$this->nodes->get( $nodeId, $settings )
		);
	}
}
