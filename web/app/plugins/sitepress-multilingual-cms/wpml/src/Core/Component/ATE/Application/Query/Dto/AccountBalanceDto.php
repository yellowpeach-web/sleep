<?php

namespace WPML\Core\Component\ATE\Application\Query\Dto;

class AccountBalanceDto {

  private $accountBalance;

  private $redirectUrl;

  private $site;

  private $token;

  private $raiseCapUrl;


  public function __construct(
    ?int $accountBalance,
    string $redirectUrl,
    string $site = '',
    string $token = '',
    string $raiseCapUrl = ''
  ) {
    $this->accountBalance = $accountBalance;
    $this->redirectUrl = $redirectUrl;
    $this->site = $site;
    $this->token = $token;
    $this->raiseCapUrl = $raiseCapUrl;
  }


  public function getAccountBalance(): ?int {
    return $this->accountBalance;
  }


  public function getRedirectUrl(): string {
    return $this->redirectUrl;
  }


  public function getSite(): string {
    return $this->site;
  }


  public function getToken(): string {
    return $this->token;
  }


  public function getRaiseCapUrl(): string {
    return $this->raiseCapUrl;
  }


}
