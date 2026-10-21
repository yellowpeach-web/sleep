<?php

namespace WPML\Core\Component\LanguageEditor\Domain\Settings;

interface SettingsInterface {


  public function defaultLanguage(): ?string;


  public function setDefaultLanguage( string $code ): void;


  public function hiddenLanguages(): array;


  public function setHiddenLanguages( array $codes ): void;


}
