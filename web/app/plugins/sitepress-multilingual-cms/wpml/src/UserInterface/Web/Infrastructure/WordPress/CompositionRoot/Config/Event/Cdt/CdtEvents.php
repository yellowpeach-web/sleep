<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\CompositionRoot\Config\Event\Cdt;

use WPML\Core\Component\Cdt\Application\Service\EventQueue;
use WPML\Core\Component\Cdt\Application\Service\HealthRefresh;
use WPML\DicInterface;
use WPML\Infrastructure\PluginFilesMatchLoadedVersionGate;
use WPML\UserInterface\Web\Infrastructure\WordPress\Events\Cdt\CdtDebugInfoListener;
use WPML\UserInterface\Web\Infrastructure\WordPress\Events\Cdt\CdtEventListener;
use WPML\UserInterface\Web\Infrastructure\WordPress\Events\Cdt\EventSenderListener;
use WPML\UserInterface\Web\Infrastructure\WordPress\Events\Cdt\CheckinAnswerListener;
use WPML\UserInterface\Web\Infrastructure\WordPress\Events\Cdt\CheckinBlockListener;
use WPML\UserInterface\Web\Infrastructure\WordPress\Events\Cdt\CheckinTriggerListener;
use WPML\UserInterface\Web\Infrastructure\WordPress\Events\Cdt\EngineOptionListener;
use WPML\UserInterface\Web\Infrastructure\WordPress\Events\Cdt\HealthRefreshListener;

class CdtEvents {

  use PluginFilesMatchLoadedVersionGate;

  const CHECKIN_REQUEST = 'installer_fetch_subscription_data_request';

  const CHECKIN_RESPONSE = 'installer_fetched_subscription_data';

  const ENGINES_UPDATED = 'wpml_tm_ate_translation_engines_updated';

  const DEBUG_INFO = 'icl_get_extra_debug_info';

  const SETUP_COMPLETED = 'wpml_setup_completed';

  const TEA_TOGGLED = 'wpml_set_translate_everything';

  const DEACTIVATED = 'wpml_deactivated';

  const TRIGGER_HOOK     = 'admin_init';
  const TRIGGER_PRIORITY = 20;

  private $dic;


  public function __construct( DicInterface $dic, ?callable $filesMatchLoadedVersion = null ) {
    $this->dic                     = $dic;
    $this->filesMatchLoadedVersion = $filesMatchLoadedVersion;
    $this->register();
  }


  public function register() {
    add_filter(
      self::CHECKIN_REQUEST,
      function ( $request ) {
        if ( ! $this->filesMatchLoadedVersion() ) {
          return $request;
        }
        return $this->dic->make( CheckinBlockListener::class )->filter( $request );
      },
      10
    );

    add_action(
      self::TRIGGER_HOOK,
      function () {
        if ( ! $this->filesMatchLoadedVersion() ) {
          return;
        }
        $this->dic->make( CheckinTriggerListener::class )->onAdminInit();
      },
      self::TRIGGER_PRIORITY
    );

    add_action(
      self::CHECKIN_RESPONSE,
      function ( $response, $repositoryId = null ) {
        if ( ! $this->filesMatchLoadedVersion() ) {
          return;
        }
        $this->dic->make( CheckinAnswerListener::class )->onFetched( $response, $repositoryId );
      },
      10,
      2
    );

    add_action(
      HealthRefresh::HOOK,
      function () {
        if ( ! $this->filesMatchLoadedVersion() ) {
          return;
        }
        $this->dic->make( HealthRefreshListener::class )->run();
      }
    );

    add_action(
      self::ENGINES_UPDATED,
      function () {
        if ( ! $this->filesMatchLoadedVersion() ) {
          return;
        }
        $this->dic->make( EngineOptionListener::class )->onEnginesUpdated();
      }
    );

    add_action(
      self::SETUP_COMPLETED,
      function () {
        if ( ! $this->filesMatchLoadedVersion() ) {
          return;
        }
        $this->dic->make( CdtEventListener::class )->onSetupCompleted();
      }
    );

    add_action(
      self::TEA_TOGGLED,
      function ( $enabled = false ) {
        if ( ! $this->filesMatchLoadedVersion() ) {
          return;
        }
        $this->dic->make( CdtEventListener::class )->onTeaToggled( $enabled );
      }
    );

    add_action(
      self::DEACTIVATED,
      function () {
        if ( ! $this->filesMatchLoadedVersion() ) {
          return;
        }
        $this->dic->make( CdtEventListener::class )->onDeactivated();
      }
    );

    add_action(
      EventQueue::HOOK,
      function () {
        if ( ! $this->filesMatchLoadedVersion() ) {
          return;
        }
        $this->dic->make( EventSenderListener::class )->run();
      }
    );

    add_filter(
      self::DEBUG_INFO,
      function ( $info ) {
        if ( ! $this->filesMatchLoadedVersion() ) {
          return $info;
        }
        return $this->dic->make( CdtDebugInfoListener::class )->filter( $info );
      }
    );
  }


}
