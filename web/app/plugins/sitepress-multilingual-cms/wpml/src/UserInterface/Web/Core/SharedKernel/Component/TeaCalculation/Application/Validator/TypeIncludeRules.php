<?php

namespace WPML\UserInterface\Web\Core\SharedKernel\Component\TeaCalculation\Application\Validator;

use DateTime;
use WPML\Core\SharedKernel\Component\Item\Application\Query\UntranslatedTypesCountQueryInterface;
use WPML\Core\SharedKernel\Component\Post\Domain\TranslateEverythingScope;
use WPML\Setup\Option;


class TypeIncludeRules {

  const ALL  = Option::SINCE_DATE_ALL;
  const NONE = Option::SINCE_DATE_SKIP_TYPE;

  const ALWAYS_ALL = [ 'page', 'string', 'wp_template', 'wp_navigation', 'Block' ];

  const ALWAYS_ALL_KINDS = [ UntranslatedTypesCountQueryInterface::KIND_TAXONOMY ];

  const NO_SINCE_OPTION = [ 'product' ];


  public function validate( $type, $includeSince, $kind = null ) {
    if ( ! is_string( $type ) || ! is_string( $includeSince ) ) {
      return __( 'Type and since date are required.', 'wpml' );
    }

    if ( ! TranslateEverythingScope::isOffered( $type ) ) {
      return sprintf(
        /* translators: Validation error about a kind of content picked for automatic translation. %s: the name of that kind of content, for example product_variation. */
        __( 'Type %s is translated with the content it belongs to and cannot be picked on its own.', 'wpml' ),
        $type
      );
    }

    if (
      in_array( $type, self::ALWAYS_ALL, true )
      || ( is_string( $kind ) && in_array( $kind, self::ALWAYS_ALL_KINDS, true ) )
    ) {
      return sprintf(
        /* translators: Validation error about a kind of content picked for automatic translation. %s: the name of that kind of content, for example post or page. */
        __( 'Type %s must always include all items.', 'wpml' ),
        $type
      );
    }

    if (
      in_array( $type, self::NO_SINCE_OPTION, true )
      && $includeSince !== self::ALL
      && $includeSince !== self::NONE
    ) {
      return sprintf(
        /* translators: Validation error about a kind of content picked for automatic translation. %s: the name of that kind of content. "Since" is the name of a setting shown next to it. */
        __( 'Type %s cannot have "Since" option.', 'wpml' ),
        $type
      );
    }

    if (
      $includeSince !== self::ALL
      && $includeSince !== self::NONE
      && ! $this->isValidDate( $includeSince )
    ) {
      return sprintf(
        /* translators: Validation error about a kind of content picked for automatic translation. %s: the name of that kind of content. YYYY-MM-DD is a date format and stays as it is. */
        __( 'Type %s has an invalid since date. Expected format: YYYY-MM-DD.', 'wpml' ),
        $type
      );
    }

    return null;
  }


  private function isValidDate( string $value ): bool {
    $date = DateTime::createFromFormat( 'Y-m-d', $value );

    return $date !== false && $date->format( 'Y-m-d' ) === $value;
  }


}
