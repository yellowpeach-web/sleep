<?php

namespace WPML\Infrastructure\WordPress\Component\StringPackage\Application\Query;

use WPML\Core\Component\StringPackage\Application\Query\PackageDefinitionQueryInterface;
use WPML\Core\Component\WordsToTranslate\Domain\StringPackage\Query\BeforeSetupPackagesQueryInterface;
use WPML\Core\SharedKernel\Component\Item\Application\Query\Dto\UntranslatedTypeCountDto;
use WPML\Core\SharedKernel\Component\Item\Application\Query\UntranslatedTypesCountQueryInterface;

class UntranslatedTypesCountBeforeSetupQuery implements UntranslatedTypesCountQueryInterface {

  private $packagesQuery;

  private $packageDefinitionQuery;


  public function __construct(
    BeforeSetupPackagesQueryInterface $packagesQuery,
    PackageDefinitionQueryInterface $packageDefinitionQuery
  ) {
    $this->packagesQuery          = $packagesQuery;
    $this->packageDefinitionQuery = $packageDefinitionQuery;
  }


  public function forKind() {
    return UntranslatedTypesCountQueryInterface::KIND_PACKAGE;
  }


  public function get( array $queryData = [] ): array {
    $perKind = [];
    foreach ( $this->packagesQuery->getAll() as $package ) {
      $slug = $package['kindSlug'];
      if ( ! isset( $perKind[ $slug ] ) ) {
        $perKind[ $slug ] = [ 'kind' => $package['kind'], 'count' => 0 ];
      }
      $perKind[ $slug ]['count']++;
    }

    if ( ! $perKind ) {
      return [];
    }

    $info = [];
    foreach ( $this->packageDefinitionQuery->getInfoList() as $key => $definition ) {
      $info[ strtolower( $key ) ] = $definition;
    }

    $dtos = [];
    foreach ( $perKind as $slug => $row ) {
      $definition = $info[ strtolower( $slug ) ] ?? null;

      $dtos[] = new UntranslatedTypeCountDto(
        $definition ? $definition->getPlural() : $row['kind'],
        $definition ? $definition->getTitle() : $row['kind'],
        $row['count'],
        UntranslatedTypesCountQueryInterface::KIND_PACKAGE,
        $slug
      );
    }

    return $dtos;
  }


  public function getSomeIds( $numberOfIdsToFetch, $offset, $type ) {
    $ids = [];
    foreach ( $this->packagesQuery->getAll() as $package ) {
      if ( $package['kindSlug'] === $type ) {
        $ids[] = $package['id'];
      }
    }

    return array_slice( $ids, $offset, $numberOfIdsToFetch );
  }


}
