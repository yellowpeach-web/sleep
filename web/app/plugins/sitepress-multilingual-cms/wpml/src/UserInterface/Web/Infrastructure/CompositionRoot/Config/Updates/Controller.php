<?php

namespace WPML\UserInterface\Web\Infrastructure\CompositionRoot\Config\Updates;

use WPML\Core\Port\PluginInterface;
use WPML\UserInterface\Web\Infrastructure\CompositionRoot\Config\ApiInterface;
use WPML\UserInterface\Web\Infrastructure\CompositionRoot\Config\UpdatesHandlerInterface;

class Controller implements UpdatesHandlerInterface {

  private $api;

  private $repository;

  private $scriptLoader;

  private $updateHandler;

  private $plugin;

  private $backgroundRun;

  private $progressNotice;


  public function __construct(
    ApiInterface $api,
    Repository $repository,
    ScriptLoader $scriptLoader,
    UpdateHandler $updateHandler,
    PluginInterface $plugin,
    BackgroundRun $backgroundRun,
    ProgressNotice $progressNotice
  ) {
    $this->api = $api;
    $this->repository = $repository;
    $this->scriptLoader = $scriptLoader;
    $this->updateHandler = $updateHandler;
    $this->plugin = $plugin;
    $this->backgroundRun = $backgroundRun;
    $this->progressNotice = $progressNotice;
  }


  public function prepareUpdates( $allUpdates ) {
    if ( ! $this->plugin->isSetupComplete() ) {
      return;
    }

    if ( $this->backgroundRun->isRunning() ) {
      $this->onBackgroundRun( $allUpdates );
      return;
    }

    if ( $this->api->isRestRequest() ) {
      $this->onRest( $allUpdates );
      return;
    }

    if ( $this->backgroundRun->isFrontEnd() ) {
      $this->backgroundRun->arm( $allUpdates );
      return;
    }

    $this->initUpdates( $allUpdates );
  }


  private function onBackgroundRun( $allUpdates ) {
    $lazyLoadedUpdates = array_filter(
      $allUpdates,
      function ( Update $update ) {
        return $update->lazyLoad();
      }
    );

    foreach ( $this->repository->getUpdatesToPerform( $lazyLoadedUpdates ) as $update ) {
      $this->updateHandler->runExclusively( $update );
    }

    $this->backgroundRun->arm( $allUpdates );
  }


  private function onRest( $allUpdates ) {
    $updatesToPerform = $this->repository->getUpdatesToPerform( $allUpdates );
    $this->updateHandler->registerRoute( $updatesToPerform, $allUpdates );
  }


  private function initUpdates( $allUpdates ) {
    $updatesToPerform = $this->repository->getUpdatesToPerform( $allUpdates );

    $lazyLoadedUpdates = [];

    foreach ( $updatesToPerform as $update ) {
      if ( $update->lazyLoad() ) {
        $lazyLoadedUpdates[ $update->id() ] = $update;
        continue;
      }

      $this->updateHandler->doUpdate( $update );
    }

    if ( $lazyLoadedUpdates ) {
      $endpoint = $this->updateHandler->endpoint();

      if ( $this->api->validateRequest( $endpoint->capabilities() ) ) {
        $this->scriptLoader->loadScript( array_keys( $lazyLoadedUpdates ), $endpoint );
      }
    }

    $this->backgroundRun->arm( $allUpdates );
    $this->showProgress( $allUpdates );
  }


  private function showProgress( $allUpdates ) {
    foreach ( $allUpdates as $update ) {
      if ( ! $update->lazyLoad() ) {
        continue;
      }

      $percent = $this->repository->getPercentWhileUnfinished( $update );
      if ( $percent === null ) {
        continue;
      }

      if ( $this->api->validateRequest( $this->updateHandler->endpoint()->capabilities() ) ) {
        $this->progressNotice->show( $percent );
      }

      return;
    }
  }


}
