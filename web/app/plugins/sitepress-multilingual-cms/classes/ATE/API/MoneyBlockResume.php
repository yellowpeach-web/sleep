<?php

namespace WPML\TM\ATE\API;

use WPML\FP\Obj;
use WPML\LIB\WP\Option;

class MoneyBlockResume {

	const OPTION = 'wpml_ate_money_block';

	const RECALL_WINDOW = 3600;

	const RECALL_FLOOR = 300;

	const REASON_SPEND_CAP = 'spend_cap';
	const REASON_DEBT = 'debt';
	const REASON_RESTRICTION = 'restriction';
	const REASON_NO_WORDS = 'no_words';

	public static function noteBlocked( $reason ) {
		$state = self::get() ?: [];
		if ( ! empty( $state['reasons'][ $reason ] ) ) {
			return;
		}
		$state['reasons'][ $reason ] = true;
		Option::updateWithoutAutoLoad( self::OPTION, $state );
	}

	public static function syncWithCredits( array $credits, callable $resumeParked, $now = null ) {
		$now     = null === $now ? time() : (int) $now;
		$present = self::reasonsInCredits( $credits );
		$state   = self::get() ?: [];
		$stored  = isset( $state['reasons'] ) && is_array( $state['reasons'] ) ? $state['reasons'] : [];

		if ( ! $state && ! $present ) {
			return;
		}

		$gone             = array_diff_key( $stored, $present );
		$state['reasons'] = array_diff_key( $stored + $present, $gone );
		if ( $gone ) {
			$state['cleared_at'] = $now;
			$state['called_at']  = 0;
		}

		if ( ! isset( $state['cleared_at'] ) ) {
			self::save( $state );

			return;
		}

		if ( $now - (int) $state['cleared_at'] >= self::RECALL_WINDOW ) {
			unset( $state['cleared_at'], $state['called_at'] );
			self::save( $state );

			return;
		}

		if ( $now - (int) $state['called_at'] < self::RECALL_FLOOR ) {
			self::save( $state );

			return;
		}

		$answer = $resumeParked();
		if ( $answer instanceof \WP_Error && 404 === (int) $answer->get_error_code() ) {
			unset( $state['cleared_at'], $state['called_at'] );
			self::save( $state );

			return;
		}

		$state['called_at'] = $now;
		self::save( $state );
	}

	public static function reasonsInCredits( array $credits ) {
		$reasons = [];

		$cap = SpendCap::fromCredits( $credits );
		if ( $cap && $cap->getUsedWords() >= $cap->getCapWords() ) {
			$reasons[ self::REASON_SPEND_CAP ] = true;
		}

		if ( (int) Obj::propOr( 0, 'subscription_debt', $credits ) > 0 || Obj::propOr( false, 'payment_required', $credits ) ) {
			$reasons[ self::REASON_DEBT ] = true;
		}

		if ( ClientRestrictionState::isRestricted() ) {
			$reasons[ self::REASON_RESTRICTION ] = true;
		}

		if ( ! Obj::propOr( false, 'pay_as_you_go', $credits )
			&& array_key_exists( 'available_balance', $credits )
			&& (int) $credits['available_balance'] <= 0
		) {
			$reasons[ self::REASON_NO_WORDS ] = true;
		}

		return $reasons;
	}

	public static function wasBlocked() {
		$state = self::get();

		return is_array( $state ) && ! empty( $state['reasons'] );
	}

	public static function get() {
		wp_cache_delete( self::OPTION, 'options' );

		$data = Option::getOr( self::OPTION, [] );

		return is_array( $data ) && $data ? $data : null;
	}

	private static function save( array $state ) {
		if ( empty( $state['reasons'] ) ) {
			unset( $state['reasons'] );
		}
		if ( ! $state ) {
			Option::delete( self::OPTION );

			return;
		}
		Option::updateWithoutAutoLoad( self::OPTION, $state );
	}
}
