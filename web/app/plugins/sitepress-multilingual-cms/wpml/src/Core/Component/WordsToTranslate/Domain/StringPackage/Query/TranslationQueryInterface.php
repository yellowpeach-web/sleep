<?php

namespace WPML\Core\Component\WordsToTranslate\Domain\StringPackage\Query;

use WPML\Core\Component\WordsToTranslate\Domain\Item;

interface TranslationQueryInterface {


  public function getLastTranslatedOriginalContent( Item $stringPackage, string $lang );


  public function getCompletedStringsOriginalContent( Item $stringPackage, string $lang );


}
