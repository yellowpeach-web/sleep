<?php
namespace WPML\StringTranslation\Infrastructure\WordPress\HookHandler\WordPress;

use WPML\StringTranslation\Infrastructure\WordPress\HookHandler\AbstractActionHookHandler;
use WPML\StringTranslation\Infrastructure\StringCore\Lock\PackTranslationsReloadLock;
use WPML\StringTranslation\Infrastructure\StringCore\Schema\StringTables;
use WPML\StringTranslation\Application\StringCore\Command\LoadExistingStringTranslationsForAllStringsCommandInterface;
use WPML\StringTranslation\Application\Setting\Repository\SettingsRepositoryInterface;

class ReloadPackTranslationsOnAdminInitAction extends AbstractActionHookHandler {
	const ACTION_NAME = 'admin_init';
	const ACTION_ARGS = 0;

	const MAX_RETRIES = 3;

	private $loadExistingStringTranslationsForAllStringsCommand;

	private $settingsRepository;

	private $lock;

	private $stringTables;

	private $ranInThisRequest = false;

	public function __construct(
		LoadExistingStringTranslationsForAllStringsCommandInterface $loadExistingStringTranslationsForAllStringsCommand,
		SettingsRepositoryInterface $settingsRepository,
		PackTranslationsReloadLock $lock,
		StringTables $stringTables
	) {
		$this->loadExistingStringTranslationsForAllStringsCommand = $loadExistingStringTranslationsForAllStringsCommand;
		$this->settingsRepository                                 = $settingsRepository;
		$this->lock                                               = $lock;
		$this->stringTables                                       = $stringTables;
	}

	protected function onAction( ...$args ) {
		if ( $this->ranInThisRequest ) {
			return;
		}

		if ( ! $this->settingsRepository->isPackTranslationsReloadPending() ) {
			return;
		}

		$this->ranInThisRequest = true;

		$missingTables = $this->stringTables->missing();
		if ( $missingTables ) {
			$this->log(
				'Notice: reloading the string translations from the language packs waits; the database tables '
				. implode( ', ', $missingTables ) . ' are missing. It runs on an admin page load once they exist.'
			);

			return;
		}

		if ( ! $this->lock->acquire() ) {
			return;
		}

		try {
			if ( ! $this->settingsRepository->claimPackTranslationsReloadPending() ) {
				return;
			}

			try {
				$this->loadExistingStringTranslationsForAllStringsCommand->run();
			} catch ( \Throwable $e ) {
				$this->onFailedReload( $e );

				return;
			}

			$this->settingsRepository->clearPackTranslationsReloadFailures();
			$this->settingsRepository->setNewTranslationsWereLoadedSetting();
			$this->settingsRepository->removeKeyFromSettings( SettingsRepositoryInterface::WAS_FRONTEND_VISITED_KEY );
		} finally {
			$this->lock->release();
		}
	}

	private function onFailedReload( \Throwable $reloadError ) {
		$reason = sprintf( '%s: %s', get_class( $reloadError ), $reloadError->getMessage() );

		try {
			$this->stringTables->forgetKnownTables();

			$failures = $this->settingsRepository->recordPackTranslationsReloadFailure();

			if ( $failures <= self::MAX_RETRIES ) {
				$this->settingsRepository->markPackTranslationsReloadPending();
				$this->log(
					sprintf(
						'Notice: reloading the string translations from the language packs failed (retry %d of %d); it runs again on the next admin page load. Reason: %s',
						$failures,
						self::MAX_RETRIES,
						$reason
					)
				);

				return;
			}

			$this->settingsRepository->clearPackTranslationsReloadFailures();
			$this->log(
				sprintf(
					'Error: reloading the string translations from the language packs failed %d times in a row; it runs again after the next language pack update. Reason: %s',
					$failures,
					$reason
				)
			);
		} catch ( \Throwable $bookkeepingError ) {
			$this->log(
				sprintf(
					'Error: reloading the string translations from the language packs failed, and it could not be scheduled again. Reason: %s; then %s: %s',
					$reason,
					get_class( $bookkeepingError ),
					$bookkeepingError->getMessage()
				)
			);
		}
	}

	private function log( string $message ) {
		error_log( '[WPML String Translation] ' . $message . ' (' . self::class . ')' );
	}
}
