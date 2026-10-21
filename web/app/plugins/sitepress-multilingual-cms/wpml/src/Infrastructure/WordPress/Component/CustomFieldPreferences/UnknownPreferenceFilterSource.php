<?php

namespace WPML\Infrastructure\WordPress\Component\CustomFieldPreferences;

use WPML\Core\Component\CustomFieldPreferences\Application\Service\UnknownPreferenceSourceInterface;

class UnknownPreferenceFilterSource implements UnknownPreferenceSourceInterface {

  const FILTER = 'wpml_resolve_custom_field_preferences';


  public function resolve( array $metaKeys, string $type ): array {
    $answers = \apply_filters( self::FILTER, [], $metaKeys, $type );

    return is_array( $answers ) ? $answers : [];
  }


}
