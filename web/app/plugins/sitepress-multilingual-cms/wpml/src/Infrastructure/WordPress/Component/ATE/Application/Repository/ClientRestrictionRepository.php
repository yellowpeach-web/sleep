<?php

namespace WPML\Infrastructure\WordPress\Component\ATE\Application\Repository;

use WPML\Core\Component\ATE\Domain\Credits\DebtorBlock;
use WPML\Core\SharedKernel\Component\ATE\Application\Repository\ClientRestrictionRepositoryInterface;
use WPML\Infrastructure\WordPress\Port\Persistence\Options;

class ClientRestrictionRepository implements ClientRestrictionRepositoryInterface {

  const OPTION_KEY = 'wpml_ate_client_restriction';

  private $options;


  public function __construct( Options $options ) {
    $this->options = $options;
  }


  public function get(): ?array {
    $value = $this->options->get( self::OPTION_KEY, null );

    if ( ! is_array( $value ) || count( $value ) === 0 ) {
      return null;
    }

    $debtorBlock = $value['debtorBlock'] ?? '';

    return in_array( $debtorBlock, DebtorBlock::BY_REASON, true )
      ? [ 'restricted' => true, 'debtorBlock' => $debtorBlock ]
      : [ 'restricted' => true ];
  }


}
