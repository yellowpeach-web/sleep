<?php

namespace WPML\TranslationPolicy;

use WPML\Upgrade\Commands\CreateEditHistoryTables;
use WPML_Upgrade_Command_Definition;
use WPML_Upgrade_Command_Factory;

class AddOnlySet {

	const SCOPES = CutoverSet::SCOPES;

	const COMMANDS = [
		CreateEditHistoryTables::class,
	];

	public static function definitions( WPML_Upgrade_Command_Factory $factory, \WPML_Upgrade_Schema $schema ): array {
		return array_map(
			fn( $command ) => $factory->create_command_definition( $command, [ $schema ], self::SCOPES ),
			self::COMMANDS
		);
	}
}
