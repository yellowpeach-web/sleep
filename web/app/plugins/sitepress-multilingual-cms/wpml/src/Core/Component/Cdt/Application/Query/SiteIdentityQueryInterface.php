<?php

namespace WPML\Core\Component\Cdt\Application\Query;

interface SiteIdentityQueryInterface {


  public function getSiteKey(): ?string;


  public function getSiteUrl(): string;


}
