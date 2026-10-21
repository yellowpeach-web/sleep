<?php

namespace WPML\TM\ATE\API;

use WPML\FP\Obj;
use WPML\LIB\WP\Option;
use WPML\TM\ATE\PullDelivery\State as DeliveryState;
use WPML\WP\OptionManager;

class SpendCapState {

	const OPTION = 'wpml_ate_spend_cap';

	public static function save( SpendCap $spendCap ) {
		Option::updateWithoutAutoLoad( self::OPTION, $spendCap->toArray() );
	}

	public static function getShown() {
		$spendCap = self::get();

		return $spendCap && $spendCap->holds() ? $spendCap : null;
	}

	public static function holds() {
		return null !== self::getShown();
	}

	public static function recordRefusal( SpendCap $refusal ) {
		$current     = self::get();
		$lastCredits = $refusal->isSighting() ? OptionManager::getOr( [], 'TM', 'Account::credits' ) : [];

		if ( $current ) {
			$next = $current->merge( $refusal );
			if ( $next === $current ) {
				return false;
			}
		} else {
			$lastReading = SpendCap::fromCredits( $lastCredits );
			$next        = $lastReading ? $refusal->merge( $lastReading ) : $refusal;
		}

		if ( $refusal->isSighting() && self::isUncappedPayAsYouGo( $lastCredits ) ) {
			$next = $next->asResumed();
		}

		self::save( $next );

		return true;
	}

	public static function get() {
		wp_cache_delete( self::OPTION, 'options' );

		$data = self::read();

		return is_array( $data ) && $data ? SpendCap::fromArray( $data ) : null;
	}

	public static function isReached() {
		wp_cache_delete( self::OPTION, 'options' );

		return (bool) self::read();
	}

	public static function clear() {
		if ( self::isReached() ) {
			Option::delete( self::OPTION );
		}
	}

	public static function syncWithCredits( array $credits ) {
		$fresh   = SpendCap::fromCredits( $credits );
		$current = self::get();

		if ( $current && self::isPayAsYouGoNoDebt( $credits ) && self::hasParkedJobs() ) {
			if ( $fresh ) {
				self::save( $current->merge( $fresh ) );
			} elseif ( ! $current->isResumed() ) {
				self::save( $current->asResumed() );
			}

			return;
		}

		self::clear();
	}

	public static function inferFromParkedJobs( array $credits, array $parkedAteIds ) {
		if ( $parkedAteIds && Obj::propOr( false, 'payment_required', $credits ) ) {
			self::rememberParkedForPayment( $parkedAteIds );

			return false;
		}

		$parkedAteIds = array_diff( $parkedAteIds, self::parkedForPayment() );
		if ( ! $parkedAteIds || ! self::isCappedPayAsYouGo( $credits ) ) {
			return false;
		}

		$current = self::get();
		if ( $current ) {
			self::save( $current->merge( SpendCap::fromCredits( $credits ) )->unresumed() );

			return true;
		}

		self::save( SpendCap::fromCredits( $credits )->asRefused() );

		return true;
	}

	const PARKED_FOR_PAYMENT_LIMIT = 500;

	private static function rememberParkedForPayment( array $ateIds ) {
		$known = array_values( array_unique( array_merge( self::parkedForPayment(), array_map( 'intval', $ateIds ) ) ) );

		DeliveryState::update( [ 'payment_parked_ate_job_ids' => array_slice( $known, -self::PARKED_FOR_PAYMENT_LIMIT ) ] );
	}

	private static function parkedForPayment(): array {
		return array_map( 'intval', (array) DeliveryState::getFresh()['payment_parked_ate_job_ids'] );
	}

	private static function hasParkedJobs() {
		$state = DeliveryState::getFresh();

		return ! empty( $state['insufficient_balance_ate_job_ids'] );
	}

	private static function isPayAsYouGoNoDebt( array $credits ) {
		return (bool) Obj::propOr( false, 'pay_as_you_go', $credits )
			&& (int) Obj::propOr( 0, 'subscription_debt', $credits ) <= 0
			&& ! Obj::propOr( false, 'payment_required', $credits );
	}

	private static function isUncappedPayAsYouGo( array $credits ) {
		return self::isPayAsYouGoNoDebt( $credits )
			&& null === SpendCap::fromCredits( $credits );
	}

	private static function isCappedPayAsYouGo( array $credits ) {
		return self::isPayAsYouGoNoDebt( $credits )
			&& null !== SpendCap::fromCredits( $credits );
	}

	private static function read() {
		return Option::getOr( self::OPTION, [] );
	}
}
