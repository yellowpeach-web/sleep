<?php

namespace WPML\EditHistory;

class PostLinksFactory implements \IWPML_Backend_Action_Loader, \IWPML_REST_Action_Loader {

	public function create() {
		if ( ! \WPML\TranslationPolicy\FeatureSwitch::isOn() ) {
			return null;
		}

		global $wpdb;

		return new PostLinks( PanelFactory::items( new WpdbStateStore( $wpdb ) ) );
	}
}
