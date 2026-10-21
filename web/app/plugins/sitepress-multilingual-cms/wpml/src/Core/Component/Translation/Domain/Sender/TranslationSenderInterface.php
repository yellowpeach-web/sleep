<?php

namespace WPML\Core\Component\Translation\Domain\Sender;

use WPML\Core\Component\Translation\Domain\Translation;
use WPML\Core\Component\Translation\Domain\TranslationBatch\TranslationBatch;

interface TranslationSenderInterface {


  public function send( TranslationBatch $batch, bool $mayTruncate = false, bool $isRetry = false, bool $sendAnyway = false ): array;


  public function cleanupAfterFailedSend( TranslationBatch $batch );


  public function getIgnoredElements(): array;


  public function wasTruncated(): bool;


  public function getFailedElements(): array;


  public function getHeldBackTranslations(): array;


}
