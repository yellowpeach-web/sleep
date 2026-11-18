<?php
namespace WPML\CLI\Core\Commands;

class RepairPageIdLinksFactory implements IWPML_Core {

	public function create() {
		global $sitepress;

		return new RepairPageIdLinks( $sitepress );
	}
}
