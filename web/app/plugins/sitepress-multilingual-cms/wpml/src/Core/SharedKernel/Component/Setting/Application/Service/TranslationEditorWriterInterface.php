<?php

namespace WPML\Core\SharedKernel\Component\Setting\Application\Service;

use WPML\Core\SharedKernel\Component\Setting\Domain\TranslationEditorSetting;

interface TranslationEditorWriterInterface {


  public function save( TranslationEditorSetting $editor );


}
