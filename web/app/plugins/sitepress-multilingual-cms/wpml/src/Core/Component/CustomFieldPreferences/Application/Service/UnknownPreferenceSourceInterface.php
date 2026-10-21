<?php

namespace WPML\Core\Component\CustomFieldPreferences\Application\Service;

interface UnknownPreferenceSourceInterface {


  public function resolve( array $metaKeys, string $type ): array;


}
