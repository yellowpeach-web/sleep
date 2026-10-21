<?php

namespace WPML\TM\ATE\API;

use WPML\Core\Component\ATE\Domain\Credits\DebtorBlock;
use WPML\FP\Obj;
use WP_Error;

class ClientRestriction {

	const ERROR_CODE  = 'CLIENT_RESTRICTED';
	const HTTP_STATUS = 428;

	private string $debtorBlock;

	private function __construct( string $debtorBlock = '' ) {
		$this->debtorBlock = $debtorBlock;
	}

	public static function fromAteError( $error ) {
		if ( ! $error instanceof WP_Error ) {
			return null;
		}

		if ( (int) $error->get_error_code() !== self::HTTP_STATUS ) {
			return null;
		}

		$errors = $error->get_error_data( $error->get_error_code() );
		if ( ! is_array( $errors ) ) {
			return null;
		}

		foreach ( $errors as $entry ) {
			$entry = (array) $entry;
			if ( self::ERROR_CODE === Obj::propOr( '', 'error_code', $entry ) ) {
				return new self( DebtorBlock::fromReason( (string) Obj::propOr( '', 'restriction_reason', $entry ) ) );
			}
		}

		return null;
	}

	public static function fromArray( array $data ) {
		$debtorBlock = (string) ( $data['debtorBlock'] ?? '' );

		return new self( in_array( $debtorBlock, DebtorBlock::BY_REASON, true ) ? $debtorBlock : '' );
	}

	public function getDebtorBlock(): string {
		return $this->debtorBlock;
	}

	public function toArray() {
		return $this->debtorBlock
			? [ 'restricted' => true, 'debtorBlock' => $this->debtorBlock ]
			: [ 'restricted' => true ];
	}
}
