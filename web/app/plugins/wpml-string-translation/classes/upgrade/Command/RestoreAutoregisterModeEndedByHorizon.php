<?php

namespace WPML\ST\Upgrade\Command;

use WPML\StringTranslation\Application\Setting\Repository\SettingsRepositoryInterface;

class RestoreAutoregisterModeEndedByHorizon implements \IWPML_St_Upgrade_Command {

	const FIRST_INSTALL_VERSION_WITH_HORIZON = '5';

	private $settingsRepository;

	public function __construct( SettingsRepositoryInterface $settingsRepository ) {
		$this->settingsRepository = $settingsRepository;
	}

	public function run() {
		$startVersion = get_option( \WPML_Installation::WPML_START_VERSION_KEY, '' );

		if (
			is_string( $startVersion )
			&& version_compare( $startVersion, self::FIRST_INSTALL_VERSION_WITH_HORIZON, '>=' )
			&& $this->settingsRepository->isAutoregisterStringsTypeDisabled()
			&& ! $this->settingsRepository->isAutoregisterModeSetByOwner()
		) {
			$this->settingsRepository->setAutoregisterStringsTypeSetting(
				SettingsRepositoryInterface::AUTOREGISTER_STRINGS_TYPE_ONLY_VIEWED_BY_ADMIN
			);
			$this->settingsRepository->saveKeyToSettings( SettingsRepositoryInterface::AUTOREGISTER_MODE_RESTORED_NOTICE_KEY, 1 );
		}

		return true;
	}

	public function run_ajax() {
		return false;
	}

	public function run_frontend() {
	}

	public static function get_command_id() {
		return __CLASS__;
	}
}
