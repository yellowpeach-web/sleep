<?php

namespace WPML\StringTranslation;

use WPML\Auryn\Injector;
use WPML\StringTranslation\Application\StringGettext\Repository\QueueRepositoryInterface;
use WPML\StringTranslation\Application\StringGettext\Repository\LoadedTextdomainRepositoryInterface;
use WPML\StringTranslation\Application\Setting\Repository\SettingsRepositoryInterface;
use WPML\StringTranslation\Application\StringHtml\Command\ProcessFrontendGettextStringsQueueInterface;
use WPML\StringTranslation\Infrastructure\StringHtml\Command\FrontendStringsObserverBridge;
use WPML\StringTranslation\Infrastructure\StringHtml\Command\ProcessFrontendGettextStringsQueue;
use WPML\StringTranslation\Infrastructure\TranslateEverything\ProcessFrontendStringsObserver;
use WPML\StringTranslation\Infrastructure\TranslateEverything\UntranslatedStrings;
use WPML\StringTranslation\Infrastructure\TranslateEverything\UntranslatedStringsFactory;
use WPML\StringTranslation\Infrastructure\WordPress\HookHandler\AbstractActionHookHandler;
use WPML\StringTranslation\Infrastructure\WordPress\HookHandler\AbstractFilterHookHandler;
use WPML\StringTranslation\Infrastructure\WordPress\HookHandler\GetSettingFilter;
use WPML\StringTranslation\Infrastructure\WordPress\HookHandler\HookHandlerInterface;
use WPML\StringTranslation\Application\WordPress\HookHandler\AutoregisterHookInterface;
use WPML\StringTranslation\Infrastructure\Factory;
use WPML\ST\Gettext\AutoRegisterSettings;
use WPML\TM\AutomaticTranslation\Actions\Actions;

class Application {

	private $implementations;

	private $hookHandlers;

	private $settings;

	private $injector;

	private $settingFilterLoaded = false;

	public function __construct( array $config = [] ) {
		$this->implementations = array_key_exists( 'interfaceMappings', $config ) ? $config['interfaceMappings'] : [];
		$this->hookHandlers    = array_key_exists( 'hookHandlers', $config ) ? $config['hookHandlers'] : [];
		$this->settings        = array_key_exists( 'settings', $config ) ? $config['settings'] : [];

		global $sitepress;
		global $wpdb;
		$filesystem = wpml_get_filesystem();

		$this->injector = new Injector();
		$this->injector->share( QueueRepositoryInterface::class );
		$this->injector->share( \WPML\StringTranslation\Application\StringCore\Command\SaveStringsGuardedCommandInterface::class );
		$this->injector->share( \WPML\StringTranslation\Application\StringCore\Command\InsertStringTranslationsGuardedCommandInterface::class );
		$this->injector->share( LoadedTextdomainRepositoryInterface::class );
		$this->injector->share( SettingsRepositoryInterface::class );
		$this->injector->defineParam( 'sitepress', $sitepress );
		$this->injector->defineParam( 'wpdb', $wpdb );
		$this->injector->defineParam( 'filesystem', $filesystem );

		$this->injector->delegate(
			\WP_Filesystem_Base::class,
			function () use ( $filesystem ) {
				return $filesystem;
			}
		);

		$this->injector->defineParam(
			'getCompletedTranslationService',
			function () {
				return \WPML\Translation\CompletedTranslationServiceFactory::create();
			}
		);

		foreach ( $this->implementations as $interfaceClass => $implementationClass ) {
			$this->injector->alias( $interfaceClass, $implementationClass );
		}

		$this->injector->delegate( AutoRegisterSettings::class, function () {
			return \WPML\Container\make( AutoRegisterSettings::class );
		} );

		$this->injector->share( FrontendStringsObserverBridge::class );
		$this->injector->delegate( FrontendStringsObserverBridge::class, function () use ( $sitepress ) {
			return new FrontendStringsObserverBridge( function () use ( $sitepress ) {
				return [
					new ProcessFrontendStringsObserver(
						( new UntranslatedStringsFactory() )->create(),
						new Actions( new \WPML_Translation_Element_Factory( $sitepress, null ) )
					),
				];
			} );
		} );

		$this->injector->delegate( ProcessFrontendGettextStringsQueueInterface::class, function () {
			return $this->injector->make( ProcessFrontendGettextStringsQueue::class, [
				':observers' => $this->injector->make( FrontendStringsObserverBridge::class )->observers(),
			] );
		} );

		$factory = new Factory( $this->injector );
		$this->injector->share( $factory );
	}

	public function loadSettingFilter() {
		if ( $this->settingFilterLoaded || ! in_array( GetSettingFilter::class, $this->hookHandlers, true ) ) {
			return;
		}

		$hookHandler = $this->injector->make( GetSettingFilter::class );
		if ( $hookHandler instanceof HookHandlerInterface ) {
			$hookHandler->load();
			$this->settingFilterLoaded = true;
		}
	}

	public function run() {
		$settingsRepository = $this->injector->make( SettingsRepositoryInterface::class );
		$ignoreIsDisabled   = $this->settings['ignoreIsDisabled'] ?? false;
		$isDisabled         = (
			$settingsRepository->isAutoregisterStringsTypeDisabled() ||
			$settingsRepository->shouldNotAutoregisterStringsFromCurrentUrl() ||
			$settingsRepository->isGuestRequestInAdminOnlyMode()
		);

		foreach ( $this->hookHandlers as $hookHandler ) {
			if ( $this->settingFilterLoaded && GetSettingFilter::class === $hookHandler ) {
				continue;
			}
			if ( $this->loadLazily( $hookHandler, $isDisabled && ! $ignoreIsDisabled ) ) {
				continue;
			}
			$hookHandler = $this->injector->make( $hookHandler );
			if ( $hookHandler instanceof HookHandlerInterface ) {
				if ( $isDisabled && ! $ignoreIsDisabled && $hookHandler instanceof AutoregisterHookInterface ) {
					continue;
				}
				$hookHandler->load();
			}
		}
	}

	private function loadLazily( $hookHandler, bool $skipAutoregister ): bool {
		if ( ! is_string( $hookHandler ) || isset( $this->implementations[ $hookHandler ] ) ) {
			return false;
		}

		foreach ( [ AbstractActionHookHandler::class, AbstractFilterHookHandler::class ] as $base ) {
			if ( ! is_subclass_of( $hookHandler, $base ) ) {
				continue;
			}
			if ( ( new \ReflectionMethod( $hookHandler, 'load' ) )->getDeclaringClass()->getName() !== $base ) {
				return false;
			}
			if ( $skipAutoregister && is_subclass_of( $hookHandler, AutoregisterHookInterface::class ) ) {
				return true;
			}

			$injector = $this->injector;
			$hookHandler::loadLazily(
				function () use ( $injector, $hookHandler ) {
					return $injector->make( $hookHandler );
				}
			);

			return true;
		}

		return false;
	}
}