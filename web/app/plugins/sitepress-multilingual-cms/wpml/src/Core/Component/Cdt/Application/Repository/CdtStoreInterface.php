<?php

namespace WPML\Core\Component\Cdt\Application\Repository;

interface CdtStoreInterface {


  public function getEngine(): ?string;


  public function hasEngine(): bool;


  public function isEngineKnown(): bool;


  public function saveEngine( ?string $engine ): void;


  public function saveEngineUnknown(): void;


  public function getFirstAutoTranslationAt(): ?int;


  public function saveFirstAutoTranslationAt( int $at ): void;


  public function getLastAutoTranslationAt(): ?int;


  public function saveLastAutoTranslationAt( int $at ): void;


  public function getHealth(): array;


  public function saveHealth( array $health ): void;


  public function getEvents( bool $fresh = false ): array;


  public function saveEvents( array $events ): void;


  public function getAnswer(): array;


  public function saveAnswer( array $answer ): void;


  public function getLastCheckinAt(): ?int;


  public function saveLastCheckinAt( int $at ): void;


  public function seedDefaults(): void;


}
