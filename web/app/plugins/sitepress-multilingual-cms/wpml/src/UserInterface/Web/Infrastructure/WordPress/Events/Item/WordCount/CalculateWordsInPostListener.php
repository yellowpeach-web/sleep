<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Events\Item\WordCount;

use WPML\Core\Port\Event\EventListenerInterface;

class CalculateWordsInPostListener implements EventListenerInterface {


  public function calculate( $currentValue, $postId ) {
    return $currentValue;
  }


}
