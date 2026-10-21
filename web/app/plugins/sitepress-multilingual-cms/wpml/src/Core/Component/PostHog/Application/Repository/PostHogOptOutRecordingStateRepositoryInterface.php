<?php

namespace WPML\Core\Component\PostHog\Application\Repository;

interface PostHogOptOutRecordingStateRepositoryInterface {


  public function activate();


  public function clear();


  public function isActive(): bool;


  public function getStartedAt(): ?int;


  public function getDashboardRecordingCount(): int;


  public function getCountedSessions(): array;


  public function hasCountedSession( string $sessionId ): bool;


  public function recordSession( string $sessionId ): bool;


  public function isCompleted(): bool;


  public function markCompleted();


  public function markPendingStop( string $reason );


  public function getPendingStopReason(): ?string;


  public function clearPendingStop();


  public function isWindowExpired(): bool;


}
