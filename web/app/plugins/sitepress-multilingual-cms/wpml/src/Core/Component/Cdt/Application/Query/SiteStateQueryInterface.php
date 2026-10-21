<?php

namespace WPML\Core\Component\Cdt\Application\Query;

interface SiteStateQueryInterface {


  public function getWpmlVersion(): string;


  public function isSetupComplete(): bool;


  public function getDefaultLanguage(): ?string;


  public function getLanguages(): ?array;


  public function isTranslateEverythingOn(): ?bool;


  public function getEditor(): ?string;


  public function isWooCommerceActive(): bool;


  public function hasConsent(): bool;


}
