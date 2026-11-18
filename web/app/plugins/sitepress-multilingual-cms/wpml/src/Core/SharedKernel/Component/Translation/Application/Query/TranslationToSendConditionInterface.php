<?php

namespace WPML\Core\SharedKernel\Component\Translation\Application\Query;

interface TranslationToSendConditionInterface {


  public function sql( string $statusAlias ): string;


  public function joblessSql( string $statusAlias ): string;


  public function joblessCutoff(): string;


  public function joinSql( string $statusAlias ): string;


  public function notDiscardedSql( string $statusAlias ): string;


  public function isRefusedPair( string $sourceLanguage, string $targetLanguage ): bool;


}
