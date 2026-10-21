<?php

namespace WPML\Core\Component\WordsToTranslate\Domain\Evidence;

class TierSelector {

  const VERBATIM_FIELD_MAX_WORDS = 20;

  const RECEIPT_THRESHOLD_NEW = 100;

  const RECEIPT_THRESHOLD_UPDATE = 250;

  const INVENTORY_MIN_CUSTOM_FIELDS = 10;


  public function select( $charged, $isFresh, $chargedCustomFieldCount, $isTermsOnly ) {
    if ( $charged === 0 ) {
      return Tier::FINGERPRINT;
    }

    if ( $isTermsOnly ) {
      return Tier::VERBATIM;
    }

    if ( $chargedCustomFieldCount >= self::INVENTORY_MIN_CUSTOM_FIELDS ) {
      return Tier::INVENTORY;
    }

    if ( $isFresh && $charged > self::RECEIPT_THRESHOLD_NEW ) {
      return Tier::RECEIPT;
    }

    return $charged > self::RECEIPT_THRESHOLD_UPDATE ? Tier::RECEIPT : Tier::DIFF;
  }


}
