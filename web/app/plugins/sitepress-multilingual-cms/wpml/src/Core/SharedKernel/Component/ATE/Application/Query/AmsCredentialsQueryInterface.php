<?php

namespace WPML\Core\SharedKernel\Component\ATE\Application\Query;

interface AmsCredentialsQueryInterface {


  public function fingerprint(): string;


  public function credentials();


  public function signatureRefused(): bool;


}
