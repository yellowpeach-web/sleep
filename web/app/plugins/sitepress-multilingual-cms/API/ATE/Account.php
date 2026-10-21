<?php

namespace WPML\TM\API\ATE;

use WPML\FP\Either;
use WPML\FP\Fns;
use WPML\FP\Obj;
use WPML\TM\ATE\API\SpendCap;
use WPML\TM\ATE\API\SpendCapState;
use WPML\LIB\WP\Transient;
use WPML\LIB\WP\WordPress;
use WPML\TM\ATE\BuyWords\ManageCreditsSite;
use WPML\TM\ATE\ClonedSites\ApiCommunication;
use WPML\TM\ATE\SpendCap\RaiseCapUrl;
use WPML\TM\ATE\ClonedSites\ReconnectState;
use WPML\WP\OptionManager;
use function WPML\Container\make;

class Account {

	const CREDITS_CACHE_KEY  = 'wpml-ate-account-credits';
	const BALANCES_CACHE_KEY = 'wpml-ate-account-balances';
	const CACHE_TTL          = 60;

	public static function getCredits( $allowCached = false, $forceProbe = false ) {
		if ( self::probeOrRefuse( $forceProbe ) ) {
			return self::creditsReadFailed( ApiCommunication::reconnectingError() );
		}

		if ( $allowCached ) {
			$cached = Transient::get( self::CREDITS_CACHE_KEY );
			if ( is_array( $cached ) ) {
				return Either::of( $cached );
			}
		}

		$amsApi  = make( \WPML_TM_AMS_API::class );
		$credits = $amsApi->getCredits();

		if ( is_wp_error( $credits ) ) {
			return self::creditsReadFailed( $credits );
		}

		if ( ! $credits ) {
			return self::creditsReadFailed( $amsApi->getLastRequestError() );
		}

		OptionManager::update( 'TM', 'Account::credits', $credits );

		SpendCapState::syncWithCredits( $credits );

		if ( self::isHealthyCreditState( $credits ) ) {
			Transient::set( self::CREDITS_CACHE_KEY, $credits, self::CACHE_TTL );
		} else {
			self::clearCache();
		}

		return Either::of( $credits );
	}

	private static function creditsReadFailed( $cause ) {
		$failure = [ 'error' => 'communication error' ];

		$reason = self::failureReason( $cause );
		if ( '' !== $reason ) {
			$failure['reason'] = $reason;
		}

		return Either::left( $failure );
	}

	private static function failureReason( $cause ) {
		if ( ! is_wp_error( $cause ) ) {
			return '';
		}

		$message = (string) $cause->get_error_message();
		$data    = $cause->get_error_data();

		if ( is_array( $data ) && ! empty( $data['upstream_status'] ) ) {
			return sprintf( 'AMS answered HTTP %d: %s', (int) $data['upstream_status'], $message );
		}

		if ( ApiCommunication::RECONNECTING_ERROR_CODE === $cause->get_error_code() ) {
			$episode = ReconnectState::get();
			$status  = is_array( $episode ) ? (int) ( $episode['last_http_status'] ?? 0 ) : 0;
			if ( $status > 0 ) {
				$service = [
					ReconnectState::SERVICE_ATE  => 'ATE ',
					ReconnectState::SERVICE_AMS  => 'AMS ',
					ReconnectState::SERVICE_BOTH => 'ATE/AMS ',
				][ (string) ( $episode['service'] ?? '' ) ] ?? '';

				return sprintf( '%s (%slast answered HTTP %d.)', $message, $service, $status );
			}
		}

		return $message;
	}

	public static function getAccountBalances( $allowCached = false, $forceProbe = false ) {
		if ( self::probeOrRefuse( $forceProbe ) ) {
			return Either::left( [ 'error' => 'communication error' ] );
		}

		if ( $allowCached ) {
			$cached = Transient::get( self::BALANCES_CACHE_KEY );
			if ( is_array( $cached ) && self::namesAnAccount( $cached ) ) {
				return Either::of( $cached );
			}
		}

		return WordPress::handleError( make( \WPML_TM_AMS_API::class )->getAccountBalances() )
		                ->filter( Fns::identity() )
						->bimap(
							function ( $response ) {
								if ( is_wp_error( $response ) ) {
									return [
										'error' => $response->get_error_message(),
									];
								}
								return $response;
							},
							function ( $balances ) {
								if ( ! is_array( $balances ) ) {
									return $balances;
								}

								$balances = ManageCreditsSite::apply( $balances );

								$credits = Transient::get( self::CREDITS_CACHE_KEY );

								$balances = RaiseCapUrl::apply( $balances, is_array( $credits ) ? $credits : [] );

								if ( is_array( $credits )
									&& self::namesAnAccount( $balances )
								) {
									Transient::set( self::BALANCES_CACHE_KEY, $balances, self::CACHE_TTL );
								}

								return $balances;
							}
						);
	}

	private static function namesAnAccount( array $balances ) {
		return array_key_exists( 'account_balance', $balances )
			&& null !== $balances['account_balance'];
	}

	private static function probeOrRefuse( $forceProbe ) {
		if ( $forceProbe ) {
			ApiCommunication::forceProbe();

			return false;
		}

		return ReconnectState::isReconnecting() && ! ReconnectState::openedByCreate();
	}

	private static function isHealthyCreditState( array $credits ) {
		$paymentProblem = (int) Obj::propOr( 0, 'subscription_debt', $credits ) > 0
			|| (bool) Obj::propOr( false, 'payment_required', $credits );

		$spendCap  = SpendCap::fromCredits( $credits );
		$atCap     = ( $spendCap && $spendCap->isReached() ) || SpendCapState::isReached();

		return ! $paymentProblem
			&& ! $atCap
			&& ( self::hasActiveSubscription( $credits ) || self::getAvailableBalance( $credits ) > 0 );
	}

	public static function clearCache() {
		Transient::delete( self::CREDITS_CACHE_KEY );
		Transient::delete( self::BALANCES_CACHE_KEY );
	}

	public static function hasActiveSubscription( array $creditInfo ) {
		return (bool) Obj::propOr( false, 'active_subscription', $creditInfo );
	}

	public static function getAvailableBalance( array $creditInfo ) {
		return (int) Obj::propOr( 0, 'available_balance', $creditInfo );
	}

	public static function isAbleToTranslateAutomatically() {
		$creditInfo = OptionManager::getOr( [], 'TM', 'Account::credits' );

		if ( ! array_key_exists( 'active_subscription', $creditInfo ) ) {
			$creditInfo = self::getCredits()->getOrElse( [] );
		}

		return self::hasActiveSubscription( $creditInfo ) || self::getAvailableBalance( $creditInfo ) > 0;
	}
}
