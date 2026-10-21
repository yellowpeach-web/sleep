<?php

namespace WPML\TM\ATE\JobCreation;

use WPML\FP\Obj;
use WPML\TM\ATE\API\ClientRestriction;
use WPML\TM\ATE\API\ErrorMessages;

class CreateFailureText {

	const RESPONSE_ATE_NOT_ACTIVE_ERROR     = 403;
	const RESPONSE_ATE_DUPLICATED_SOURCE_ID = 417;
	const RESPONSE_ATE_CLIENT_RESTRICTED    = 428;
	const RESPONSE_ATE_UNEXPECTED_ERROR     = 500;

	const NO_ANSWER_ERROR_CODE = 'wpml_ate_reconcile_no_answer';

	public static function from( \WP_Error $error ) {
		return self::render( $error )[0];
	}

	public static function statusOf( \WP_Error $error ) {
		return self::render( $error )[1];
	}

	public static function noAnswerError() {
		return new \WP_Error( self::NO_ANSWER_ERROR_CODE, '', [ 'status' => 503 ] );
	}

	private static function render( \WP_Error $error ) {
		$code    = 0;
		$message = $error->get_error_message();
		if ( $error->error_data && is_array( $error->error_data ) ) {
			foreach ( $error->error_data as $error_data ) {
				$code    = (int) Obj::pathOr( 0, [ 0, 'status' ], $error_data );
				$message = ( $code ? $code . ' ' : '' ) . Obj::pathOr( '', [ 0, 'message' ], $error_data ) . "\n\n";

				switch ( $code ) {
					case self::RESPONSE_ATE_NOT_ACTIVE_ERROR:
						$message .= ErrorMessages::ateNotActive();
						break;
					case self::RESPONSE_ATE_CLIENT_RESTRICTED:
						$restriction = ClientRestriction::fromAteError( $error );
						if ( ! $restriction ) {
							$message .= __( 'Advanced Translation Editor error.', 'sitepress' );
						} elseif ( $restriction->getDebtorBlock() ) {
							$message = ErrorMessages::clientRestrictedByUnpaidBalance( $restriction->getDebtorBlock() );
						} else {
							$message = ErrorMessages::clientRestricted();
						}
						break;
					case self::RESPONSE_ATE_DUPLICATED_SOURCE_ID:
					case self::RESPONSE_ATE_UNEXPECTED_ERROR:
					default:
						$message .= __( 'Advanced Translation Editor error.', 'sitepress' );
				}
			}
		}

		return [ $message, $code ];
	}
}
