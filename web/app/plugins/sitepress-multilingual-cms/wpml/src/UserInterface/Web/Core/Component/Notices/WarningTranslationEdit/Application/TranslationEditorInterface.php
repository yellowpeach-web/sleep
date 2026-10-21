<?php

namespace WPML\UserInterface\Web\Core\Component\Notices\WarningTranslationEdit\Application;

interface TranslationEditorInterface {


  public function getTranslationEditorLink( int $postId ): string;


  public function usesNativeEditor( int $postId ): bool;


  public function hasLocalJobInProgress( int $postId ): bool;


}
