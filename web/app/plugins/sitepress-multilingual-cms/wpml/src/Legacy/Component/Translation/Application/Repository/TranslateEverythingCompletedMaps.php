<?php

namespace WPML\Legacy\Component\Translation\Application\Repository;

use WPML\Core\Component\Translation\Application\Repository\TranslateEverythingCompletedMapsInterface;
use WPML\Core\Port\Persistence\Exception\DatabaseErrorException;
use WPML\Core\Port\Persistence\QueryHandlerInterface;
use WPML\Core\Port\Persistence\QueryPrepareInterface;
use WPML\TM\ATE\TranslateEverything\CompletedMap;
use WPML\TM\ATE\TranslateEverything\UntranslatedPackages;
use WPML\TM\ATE\TranslateEverything\UntranslatedTerms;
use WPML\TM\Jobs\JobLog;
use function WPML\Container\make;

class TranslateEverythingCompletedMaps implements TranslateEverythingCompletedMapsInterface {

  private $queryPrepare;

  private $queryHandler;

  private $terms;

  public function __construct( QueryPrepareInterface $queryPrepare, QueryHandlerInterface $queryHandler, ?UntranslatedTerms $terms = null ) {
    $this->queryPrepare = $queryPrepare;
    $this->queryHandler = $queryHandler;
    $this->terms        = $terms;
  }


  public function removeTypes( string $map, array $types ) {
    if ( ! $types ) {
      return;
    }

    $isPackages = $map === TranslateEverythingCompletedMapsInterface::PACKAGES;

    CompletedMap::update(
      $map,
      function ( array $completed ) use ( $types, $isPackages ) {
        foreach ( $types as $type ) {
          unset( $completed[ $isPackages ? UntranslatedPackages::kindKey( $type ) : $type ] );
        }

        return $completed;
      }
    );
  }

  public function reopenTaxonomiesForJobs( array $jobIds ): void {
    $jobIds = array_values( array_unique( array_filter( array_map( 'intval', $jobIds ) ) ) );
    if ( ! $jobIds ) {
      return;
    }

    try {
      $rows = $this->queryHandler->query(
        $this->queryPrepare->prepare(
          "SELECT DISTINCT tt.taxonomy
          FROM {$this->queryPrepare->prefix()}icl_translate unit
          INNER JOIN {$this->queryPrepare->prefix()}term_taxonomy tt ON unit.field_type LIKE 't\_%'
          AND tt.term_taxonomy_id = CAST(SUBSTRING(unit.field_type, 3) AS UNSIGNED)
          WHERE unit.job_id IN (" . implode( ',', array_fill( 0, count( $jobIds ), '%d' ) ) . ')',
          ...$jobIds
        )
      )->getResults();
    } catch ( \InvalidArgumentException | DatabaseErrorException $error ) {
      JobLog::addError( 'tea_taxonomy_reopen_read_failed', [ 'job_ids' => $jobIds ] );
      return;
    }

    $terms = $this->terms ?: make( UntranslatedTerms::class );
    foreach ( array_unique( array_column( $rows, 'taxonomy' ) ) as $taxonomy ) {
      $terms->markTaxonomyAsUncompleted( (string) $taxonomy );
    }
  }


}
