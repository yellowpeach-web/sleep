<?php
namespace WPML\CLI\Core\Commands;

class ConfigReloadFactory implements IWPML_Core {

	public function create() {
		global $sitepress;

		return new ConfigReload( new \WPML_Config_Update( $sitepress, new \WP_Http() ) );
	}
}
