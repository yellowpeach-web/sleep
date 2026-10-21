<?php

namespace WPML\Core\Component\ATE\Application\Query\Dto;

class CreditInfoDto {

  private $freeCreditsAmount;

  private $activeSubscription;

  private $subscriptionMaxLimit;

  private $subscriptionUsage;

  private $availableBalance;

  private $totalCreditsDeposited;

  private $totalCreditsSpent;

  private $payAsYouGo;

  private $subscriptionDebt;

  private $hasSoftware;

  private $billingContactMissing;

  private $billingContactUnreachable;

  private $siteSpendCap;

  private $debtorBlock;


  public function __construct(
    int $freeCreditsAmount,
    bool $activeSubscription,
    int $subscriptionUsage,
    int $availableBalance,
    int $totalCreditsDeposited,
    int $totalCreditsSpent,
    bool $payAsYouGo,
    ?int $subscriptionMaxLimit = null,
    int $subscriptionDebt = 0,
    bool $hasSoftware = false,
    bool $billingContactMissing = false,
    bool $billingContactUnreachable = false,
    ?array $siteSpendCap = null,
    string $debtorBlock = ''
  ) {
    $this->freeCreditsAmount         = $freeCreditsAmount;
    $this->activeSubscription        = $activeSubscription;
    $this->subscriptionMaxLimit      = $subscriptionMaxLimit;
    $this->subscriptionUsage         = $subscriptionUsage;
    $this->availableBalance          = $availableBalance;
    $this->totalCreditsDeposited     = $totalCreditsDeposited;
    $this->totalCreditsSpent         = $totalCreditsSpent;
    $this->payAsYouGo                = $payAsYouGo;
    $this->subscriptionDebt          = $subscriptionDebt;
    $this->hasSoftware               = $hasSoftware;
    $this->billingContactMissing     = $billingContactMissing;
    $this->billingContactUnreachable = $billingContactUnreachable;
    $this->siteSpendCap              = $siteSpendCap;
    $this->debtorBlock               = $debtorBlock;
  }


  public function getFreeCreditsAmount(): int {
    return $this->freeCreditsAmount;
  }


  public function getActiveSubscription(): bool {
    return $this->activeSubscription;
  }


  public function getSubscriptionMaxLimit() {
    return $this->subscriptionMaxLimit;
  }


  public function getSubscriptionUsage(): int {
    return $this->subscriptionUsage;
  }


  public function getAvailableBalance(): int {
    return $this->availableBalance;
  }


  public function getTotalCreditsDeposited(): int {
    return $this->totalCreditsDeposited;
  }


  public function getTotalCreditsSpent(): int {
    return $this->totalCreditsSpent;
  }


  public function getPayAsYouGo(): bool {
    return $this->payAsYouGo;
  }


  public function getSubscriptionDebt(): int {
    return $this->subscriptionDebt;
  }


  public function getHasSoftware(): bool {
    return $this->hasSoftware;
  }


  public function getBillingContactMissing(): bool {
    return $this->billingContactMissing;
  }


  public function getBillingContactUnreachable(): bool {
    return $this->billingContactUnreachable;
  }


  public function getSiteSpendCap(): ?array {
    return $this->siteSpendCap;
  }


  public function getDebtorBlock(): string {
    return $this->debtorBlock;
  }


}
