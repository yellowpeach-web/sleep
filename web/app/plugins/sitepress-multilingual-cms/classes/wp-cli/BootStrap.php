<?php

namespace WPML\CLI\Core;

use WPML\CLI\Core\Commands\AppearanceFactory;
use WPML\CLI\Core\Commands\ClearCacheFactory;
use WPML\CLI\Core\Commands\ConfigReloadFactory;
use WPML\CLI\Core\Commands\MigrateLanguagesFactory;
use WPML\CLI\Core\Commands\RemoveTranslationPriorityFactory;
use WPML\CLI\Core\Commands\RepairPageIdLinksFactory;
use WPML\CLI\Core\Commands\TranslationPolicyCutoverFactory;
use WPML\CLI\Core\Commands\ICommand;

class BootStrap {
	const MAIN_COMMAND = 'wpml';

	public function init() {
		$commands_factory = [
			ClearCacheFactory::class,
			MigrateLanguagesFactory::class,
			AppearanceFactory::class,
			TranslationPolicyCutoverFactory::class,
			RepairPageIdLinksFactory::class,
			RemoveTranslationPriorityFactory::class,
			ConfigReloadFactory::class,
		];

		foreach ( $commands_factory as $command_factory ) {
			$command_factory_obj = new $command_factory();

			$command = $command_factory_obj->create();

			$this->add_command( $this->getFullCommand( $command ), $command );
		}
	}

	private function add_command( $command_text, $command ) {
		$args = is_object( $command ) && method_exists( $command, 'get_help' ) ? (array) $command->get_help() : [];
		\WP_CLI::add_command( $command_text, $command, $args );
	}

	private function getFullCommand( $command ) {
		return trim( self::MAIN_COMMAND . ' ' . $command->get_command() );
	}
}
