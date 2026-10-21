<?php

namespace WPML\Core\Component\LanguageEditor\Domain\Ate;

interface AteMappingInterface {


  public function saveMapping( string $code, string $ateLang, ?string $ateCountry ): ?int;


}
