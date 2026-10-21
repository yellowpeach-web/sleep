<?php

namespace WPML\Legacy\Component\Post\Application\Query;

use WPML\Core\SharedKernel\Component\Post\Application\Hook\PostTypeFilterInterface;
use WPML\Core\SharedKernel\Component\Post\Application\Query\Dto\PostTypeDto;
use WPML\Core\SharedKernel\Component\Post\Application\Query\TranslatableTypesQueryInterface;

class TranslatableTypesQuery implements TranslatableTypesQueryInterface {

  private $sitepress;

  private $postTypeFilter;

  private $postTypeObjects;


  public function __construct(
    \SitePress $sitepress,
    ?PostTypeFilterInterface $postTypeFilter = null,
    ?PostTypeObjectCache $postTypeObjects = null
  ) {
    $this->sitepress       = $sitepress;
    $this->postTypeFilter  = $postTypeFilter;
    $this->postTypeObjects = $postTypeObjects ?: new PostTypeObjectCache();
  }


  public function getTranslatable(): array {
    $postTypes = array_keys( $this->getFilteredTranslatablePostTypes() );

    return $this->buildCollection( $postTypes );
  }


  public function getDisplayAsTranslated(): array {
    $postTypes = array_keys( $this->getFilteredDisplayAsTranslatedPostTypes() );

    return $this->buildCollection( $postTypes );
  }


  public function getTranslatableWithoutDisplayAsTranslated(): array {
    $allTranslatable = array_keys(
      $this->getFilteredTranslatablePostTypes()
    );
    $displayAsTranslated = array_keys(
      $this->sitepress->get_display_as_translated_documents()
    );

    $postTypes = array_diff( $allTranslatable, $displayAsTranslated );

    return $this->buildCollection( $postTypes );
  }


  public function isTranslatableInSettings( string $postType ): bool {
    $syncOptions = (array) $this->sitepress->get_setting( 'custom_posts_sync_option', [] );

    $syncSettings = new \WPML_Element_Sync_Settings( $syncOptions );

    return $syncSettings->is_sync( $postType );
  }


  private function buildCollection( array $postTypes ): array {
    $displayAsTranslated = array_keys( $this->sitepress->get_display_as_translated_documents() );
    $collection = [];

    foreach ( $postTypes as $postType ) {
      try {
        $isDisplayAsTranslated = in_array( $postType, $displayAsTranslated, true );
        $collection[] = $this->buildItemTypeDetails( $postType, $isDisplayAsTranslated );
      } catch ( \InvalidArgumentException $e ) {
      }
    }

    return $collection;
  }


  private function buildItemTypeDetails( string $postType, bool $isDisplayAsTranslated = false ): PostTypeDto {
    $postTypeObject = $this->getFilteredPostTypeObject( $postType );

    return new PostTypeDto(
      $postTypeObject->name,
      $postTypeObject->labels->name,
      $postTypeObject->labels->singular_name,
      $postTypeObject->labels->name,
      $postTypeObject->hierarchical,
      $postTypeObject->public,
      $postTypeObject->show_ui,
      $isDisplayAsTranslated
    );
  }


  private function getFilteredPostTypeObject( string $postType ) {
    $cached = $this->postTypeObjects->get( $postType );
    if ( $cached ) {
      return $cached;
    }

    $postTypeObject = get_post_type_object( $postType );

    if ( ! $postTypeObject ) {
      throw new \InvalidArgumentException( "Post type $postType does not exist" );
    }

    $postTypeObject = apply_filters( 'wpml_post_type_dto_filter', $postTypeObject );
    $this->postTypeObjects->set( $postType, $postTypeObject );

    return $postTypeObject;
  }


  private function getFilteredDisplayAsTranslatedPostTypes(): array {
    $postTypes = $this->sitepress->get_display_as_translated_documents();
    if ( ! $this->postTypeFilter ) {
      return $postTypes;
    }
    return $this->postTypeFilter->filter( $postTypes );
  }


  private function getFilteredTranslatablePostTypes(): array {
    $postTypes = $this->sitepress->get_translatable_documents();
    if ( ! $this->postTypeFilter ) {
      return $postTypes;
    }
    return $this->postTypeFilter->filter( $postTypes );
  }


}
