<?php

namespace WPML\Legacy\Component\Translation\Sender;

use WPML\Core\Component\Translation\Domain\TranslationBatch\TranslationBatch;

interface AteResendGuardInterface {


  public function duringUserSend( callable $call );


  public function prepare( TranslationBatch $batch, bool $isRetry, bool $sendAnyway = false ): AteResendGuardResult;


}
