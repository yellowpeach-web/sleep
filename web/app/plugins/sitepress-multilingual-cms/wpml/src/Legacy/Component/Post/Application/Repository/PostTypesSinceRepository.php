<?php

namespace WPML\Legacy\Component\Post\Application\Repository;

use WPML\Setup\Option;
use WPML\TM\ATE\TranslateEverything\UntranslatedPackages;
use WPML\TM\ATE\TranslateEverything\UntranslatedPosts;
use WPML\UserInterface\Web\Core\SharedKernel\Component\TeaCalculation\Application\Repository\PostTypesSinceRepositoryInterface;
use WPML\WP\OptionManager;


class PostTypesSinceRepository implements PostTypesSinceRepositoryInterface {

  private $untranslatedPosts;

  private $untranslatedPackages;

  private $optionManager;


  public function __construct(
    UntranslatedPosts $untranslatedPosts,
    OptionManager $optionManager,
    UntranslatedPackages $untranslatedPackages
  ) {
    $this->untranslatedPosts    = $untranslatedPosts;
    $this->optionManager        = $optionManager;
    $this->untranslatedPackages = $untranslatedPackages;
  }


  public function getPostTypesSinceDates(): array {
    return Option::getTranslateEverythingPostsSinceDates();
  }


  public function updatePostTypeSinceDate( string $type, string $since ): void {
    $this->mutateSinceDates( fn( array $fresh ) => [ $type => $since ] + $fresh );
  }


  public function applySinceDates( array $sinceDatesByType ): array {
    if ( ! $sinceDatesByType ) {
      return [];
    }

    $changed = [];

    $this->mutateSinceDates(
      function ( array $fresh ) use ( $sinceDatesByType, &$changed ): array {
        foreach ( $sinceDatesByType as $type => $since ) {
          if ( ( $fresh[ $type ] ?? null ) !== $since ) {
            $changed[ $type ] = $since;
          }
        }

        return $changed + $fresh;
      }
    );

    return $changed;
  }


  public function holdBack( array $types ): void {
    if ( ! $types ) {
      return;
    }

    $this->mutateSinceDates(
      fn( array $fresh ) => array_fill_keys( $types, Option::SINCE_DATE_SKIP_TYPE ) + $fresh
    );
  }


  public function discard( array $types ): void {
    if ( ! $types ) {
      return;
    }
    $this->mutateSinceDates(
      fn( array $fresh ) => array_diff_key( $fresh, array_fill_keys( $types, true ) )
    );
  }

  public function fillMissingSinceDates( array $types, string $default = Option::SINCE_DATE_ALL ): void {
    if ( ! $types ) {
      return;
    }

    $this->mutateSinceDates(
      fn( array $fresh ) => $fresh + array_fill_keys( $types, $default )
    );
  }


  public function markPostTypeAsUncompleted( string $type ): void {
    $this->untranslatedPosts->markPostTypeAsUncompleted( $type );
  }


  public function markPostTypeAsCompleted( string $type ): void {
    $this->untranslatedPosts->markTypeAsCompleted( $type );
  }


  public function markPackageKindAsUncompleted( string $kind ): void {
    $this->untranslatedPackages->markKindAsUncompleted( $kind );
  }


  public function markPackageKindAsCompleted( string $kind ): void {
    $this->untranslatedPackages->markTypeAsCompleted( $kind );
  }


  private function mutateSinceDates( callable $updater ): void {
    $this->optionManager->mutate(
      Option::OPTION_GROUP,
      Option::TRANSLATE_EVERYTHING_POSTS_SINCE_DATES,
      fn( $current ): array => $updater( is_array( $current ) ? $current : [] )
    );
  }


}
