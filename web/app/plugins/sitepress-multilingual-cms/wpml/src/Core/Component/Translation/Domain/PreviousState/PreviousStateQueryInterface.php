<?php

namespace WPML\Core\Component\Translation\Domain\PreviousState;

interface PreviousStateQueryInterface {


  public function getByJobId( int $jobId );


  public function getByRID( int $rid );


  public function getByTranslationId( int $translationId );


  public function hasTranslatedElement( int $translationId ): bool;


  public function getTranslationIdsByRids( array $rids, array $statuses ): array;


}
