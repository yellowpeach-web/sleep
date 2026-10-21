<?php

namespace WPML\Infrastructure\WordPress\Component\StringPackage\Application\Query;

use WPML\Core\Component\StringPackage\Application\Query\Dto\PackageDefinitionDto;
use WPML\Core\Component\StringPackage\Application\Query\PackageDefinitionQueryInterface;

class PackageDefinitionQuery implements PackageDefinitionQueryInterface {


  public function getInfoList(): array {
    $packageDefinitions = [];

    foreach ( $this->callFilter() as $slug => $info ) {
      $key = (string) $slug;
      $dto = $this->normalize( $key, $info );

      if ( $dto ) {
        $packageDefinitions[ $key ] = $dto;
      }
    }

    return $packageDefinitions;
  }


  private function normalize( string $key, $info ): ?PackageDefinitionDto {
    if ( is_array( $info ) ) {
      $title  = $info['title'] ?? null;
      $slug   = $info['slug'] ?? null;
      $plural = $info['plural'] ?? null;
    } elseif ( is_scalar( $info ) ) {
      $title  = $info;
      $slug   = $key;
      $plural = $info;
    } else {
      return null;
    }

    $slug   = $this->usable( $slug ) ?? $key;
    $title  = $this->usable( $title ) ?? $slug;
    $plural = $this->usable( $plural ) ?? $title;

    return new PackageDefinitionDto( $title, $slug, $plural );
  }


  private function usable( $value ): ?string {
    if ( ! is_scalar( $value ) ) {
      return null;
    }

    $value = (string) $value;

    return $value === '' ? null : $value;
  }


  public function isPackageOnTheList( string $packageKindSlug ): bool {
    $list = $this->getNamesList();

    $lowercaseList = array_map( 'strtolower', $list );

    return in_array( strtolower( $packageKindSlug ), $lowercaseList, true );
  }


  public function getNamesList(): array {
    return array_map( 'strval', array_keys( $this->callFilter() ) );
  }


  private function callFilter(): array {
    $packages = \apply_filters( 'wpml_active_string_package_kinds', [] );

    return is_array( $packages ) ? $packages : [];
  }


}
