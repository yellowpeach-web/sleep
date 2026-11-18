<?php

namespace WPML\TM\ATE\Hooks;

use WPML\TM\API\ATE\LanguageMappings;
use WPML\Upgrade\Commands\RemoveLanguageMappingsOntoDefaultLanguage;
use WPML\Upgrade\Commands\RemoveSwappedLanguageMappingPairs;
use WPML\Upgrade\CommandsStatus;

class LanguageMappingsOnDefaultChange implements \IWPML_Backend_Action, \IWPML_REST_Action {

	const COMMANDS = [
		RemoveLanguageMappingsOntoDefaultLanguage::class,
		RemoveSwappedLanguageMappingPairs::class,
	];

	private $commandsStatus;

	public function __construct( ?CommandsStatus $commandsStatus = null ) {
		$this->commandsStatus = $commandsStatus;
	}

	public function add_hooks() {
		add_action( 'icl_after_set_default_language', [ $this, 'reArmRepair' ], 10, 2 );
	}

	public function reArmRepair( $code, $previous = null ) {
		$code     = is_string( $code ) ? $code : '';
		$previous = is_string( $previous ) ? $previous : '';

		if ( '' === $code || $code === $previous ) {
			return;
		}

		$status = $this->commandsStatus ?: new CommandsStatus();
		foreach ( self::COMMANDS as $command ) {
			$status->clearExecuted( $command );
			delete_transient( $command::RETRY_TRANSIENT );
			delete_option( $command::ATTEMPTS_OPTION );
		}

		LanguageMappings::logLine(
			__( 'The default language changed: the removal of language mappings onto the default language runs again.', 'sitepress' ),
			[
				'from' => $previous,
				'to'   => $code,
			]
		);
	}
}
