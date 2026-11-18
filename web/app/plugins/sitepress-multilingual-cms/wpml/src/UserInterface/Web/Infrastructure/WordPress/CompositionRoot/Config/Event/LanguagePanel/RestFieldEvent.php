<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\CompositionRoot\Config\Event\LanguagePanel;

use WPML\Core\Component\Post\Application\LanguagePanel\EditorSaveRequestInterface;
use WPML\Core\Component\Post\Application\LanguagePanel\TranslatableRestPostTypesQueryInterface;
use WPML\DicInterface;
use WPML\Infrastructure\PluginFilesMatchLoadedVersionGate;
use WPML\Infrastructure\WordPress\Component\LanguagePanel\RestField\PostLanguagePanelRestField;
use WPML\Infrastructure\WordPress\Component\LanguagePanel\Save\EditorSaveContext;
use WPML\Infrastructure\WordPress\Component\LanguagePanel\Save\EditorSaveSlugFix;
use WPML\Legacy\Component\Post\Application\LanguagePanel\EditorSaveRequest;

class RestFieldEvent {

  use PluginFilesMatchLoadedVersionGate;

  private $dic;


  public function __construct( DicInterface $dic, ?callable $filesMatchLoadedVersion = null ) {
    $this->dic                     = $dic;
    $this->filesMatchLoadedVersion = $filesMatchLoadedVersion;
    $this->register();
  }


  public function register() {
    add_action( 'rest_api_init', [ $this, 'onRestApiInit' ] );
  }


  public function onRestApiInit() {
    if ( ! $this->filesMatchLoadedVersion() ) {
      return;
    }

    $detector = new EditorSaveRequest();
    $this->dic->share( $detector );
    $this->dic->alias( EditorSaveRequestInterface::class, EditorSaveRequest::class );

    add_filter( 'rest_request_before_callbacks', [ $detector, 'inspect' ], 10, 3 );
    add_filter( 'rest_request_after_callbacks', [ $detector, 'conclude' ], 10, 3 );

    $postTypes = $this->dic->make( TranslatableRestPostTypesQueryInterface::class );
    $this->dic->share( $postTypes );

    $context = $this->dic->make( EditorSaveContext::class );
    $this->dic->share( $context );
    $context->register();

    $slugFix = $this->dic->make( EditorSaveSlugFix::class );
    $slugFix->register();

    $field = $this->dic->make( PostLanguagePanelRestField::class );
    $field->register();
  }


}
