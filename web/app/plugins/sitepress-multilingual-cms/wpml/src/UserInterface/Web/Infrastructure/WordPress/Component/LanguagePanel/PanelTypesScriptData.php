<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\LanguagePanel;

use WPML\Core\Component\Post\Application\LanguagePanel\EditorScreenQueryInterface;

final class PanelTypesScriptData {


  public static function of( EditorScreenQueryInterface $screen, array $types ): array {
    $data = [];
    foreach ( $types as $type ) {
      $data[ $type ] = [
        'rest'   => $screen->restRoute( $type ),
        'labels' => $screen->postTypeLabels( $type ),
      ];
    }

    return $data;
  }


}
