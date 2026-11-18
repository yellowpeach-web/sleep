<?php
namespace WPML\CLI\Core\Commands;

use WPML\Upgrade\CommandsStatus;

class RemoveTranslationPriorityFactory implements IWPML_Core {

	public function create() {
		global $sitepress;

		return new RemoveTranslationPriority( wpml_get_upgrade_schema(), $sitepress, new CommandsStatus() );
	}
}
