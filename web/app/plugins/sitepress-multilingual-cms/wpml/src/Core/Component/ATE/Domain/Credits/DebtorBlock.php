<?php

namespace WPML\Core\Component\ATE\Domain\Credits;

final class DebtorBlock {

  const CARD          = 'card';
  const BILLING_EMAIL = 'billing-email';

  const BY_REASON = [
    'card_with_debtor'          => self::CARD,
    'billing_email_with_debtor' => self::BILLING_EMAIL,
  ];


  public static function fromCredits( array $credits ): string {
    $details = $credits['payment_required_details'] ?? null;
    if (
      empty( $credits['payment_required'] )
      || ! is_array( $details )
      || ( $details['reason'] ?? '' ) !== 'blocked'
      || ! is_array( $details['details'] ?? null )
    ) {
      return '';
    }

    $reasons = array_map(
      fn( $restriction ) => is_array( $restriction ) ? (string) ( $restriction['reason'] ?? '' ) : '',
      $details['details']
    );

    foreach ( self::BY_REASON as $reason => $block ) {
      if ( in_array( $reason, $reasons, true ) ) {
        return $block;
      }
    }

    return '';
  }


  public static function fromReason( string $reason ): string {
    return self::BY_REASON[ $reason ] ?? '';
  }


}
