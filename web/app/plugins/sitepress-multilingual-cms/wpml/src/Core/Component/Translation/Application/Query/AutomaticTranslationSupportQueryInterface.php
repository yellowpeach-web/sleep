<?php

namespace WPML\Core\Component\Translation\Application\Query;

interface AutomaticTranslationSupportQueryInterface {


  public function supportFrom( string $sourceLanguageCode ): array;


}
