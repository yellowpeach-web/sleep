<?php

namespace WPML\Legacy\Component\Translation\Domain\Links;

use WPML\Core\Component\Translation\Domain\Links\CollectorInterface;
use WPML\Core\Component\Translation\Domain\Links\ContentLinks;
use WPML\Core\Component\Translation\Domain\Links\Item;
use WPML\Core\Component\Translation\Domain\Links\RepositoryInterface;
use WPML\PHP\Exception\InvalidTypeException;

class Collector implements CollectorInterface {

  private $repostiory;

  private $items = [];


  public function __construct( RepositoryInterface $repository ) {
    $this->repostiory = $repository;
  }


  public function getItemsLinkedInContent( string $content ) {
    $this->items = [];
    if ( class_exists( '\AbsoluteLinks' ) ) {
        $absoluteLinks = new \AbsoluteLinks();
        $brokenLinks   = [];
        $absoluteLinks->_process_generic_text( $content, $brokenLinks, false, $this, $this->languageScopeOf( $content ) );
    }

    return array_values( $this->collectedItems() );
  }


  private function collectedItems() {
    return $this->items;
  }


  private function languageScopeOf( string $content ) {
    $languages = apply_filters( 'wpml_active_languages', [] );
    $active    = array_keys( $languages );
    if ( ! $active ) {
      return null;
    }

    $posted = filter_input( INPUT_POST, 'icl_post_language', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
    $current = is_string( $posted ) && '' !== $posted ? $posted : apply_filters( 'wpml_current_language', null );
    $default = apply_filters( 'wpml_default_language', null );

    $urls  = ContentLinks::signature( $content );
    $named = ContentLinks::languagesNamedIn( $urls, $active, $this->hostToLang( $urls, $active, $default ) );

    $scope = [];
    foreach ( array_merge( [ $current, $default ], $named ) as $lang ) {
      if ( is_string( $lang ) && in_array( $lang, $active, true ) ) {
        $scope[] = $lang;
      }
    }
    $scope = array_values( array_unique( $scope ) );

    return $scope ? $scope : null;
  }


  private function hostToLang( array $urls, array $active, $default ): array {
    if ( ! $default || ! function_exists( 'home_url' ) ) {
      return [];
    }

    $defaultUrl  = apply_filters( 'wpml_permalink', home_url(), $default );
    $defaultHost = parse_url( $defaultUrl, PHP_URL_HOST );

    $foreign = false;
    foreach ( $urls as $url ) {
      $host = parse_url( $url, PHP_URL_HOST );
      if ( $host && $host !== $defaultHost ) {
        $foreign = true;
        break;
      }
    }
    if ( ! $foreign ) {
      return [];
    }

    $map = [];
    foreach ( $active as $code ) {
      $languageUrl = apply_filters( 'wpml_permalink', home_url(), $code );
      $host = parse_url( $languageUrl, PHP_URL_HOST );
      if ( $host && $host !== $defaultHost ) {
        $map[ $host ] = $code;
      }
    }

    return $map;
  }


  public function addItemByIdAndType( int $id, string $type ) {
    $key = $type . '-' . $id;
    if ( isset( $this->items[ $key ] ) ) {
      return;
    }

    try {
      $this->items[ $key ] = $this->repostiory->get( $id, $type );
    } catch ( InvalidTypeException $e ) {
      return;
    }
  }


}
