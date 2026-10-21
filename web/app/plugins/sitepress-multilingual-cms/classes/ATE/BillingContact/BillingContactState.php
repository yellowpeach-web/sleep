<?php

namespace WPML\TM\ATE\BillingContact;

use WPML\WP\OptionManager;

class BillingContactState {

	const OPTION = 'wpml_ate_billing_contact_notice';

	const AMS_FIELD             = 'billing_contact_missing';
	const AMS_FIELD_UNREACHABLE = 'billing_contact_unreachable';

	const REASON_MISSING     = 'missing';
	const REASON_UNREACHABLE = 'unreachable';

	private static function amsFields() {
		return array(
			self::REASON_MISSING     => self::AMS_FIELD,
			self::REASON_UNREACHABLE => self::AMS_FIELD_UNREACHABLE,
		);
	}

	public static function reasons() {
		return array_keys( self::amsFields() );
	}

	public static function isFlagged( $reason ) {
		$field = self::fieldFor( $reason );

		if ( '' === $field ) {
			return false;
		}

		$credits = self::storedCredits();

		return ! empty( $credits[ $field ] );
	}

	public static function isResolved( $reason ) {
		$field = self::fieldFor( $reason );

		if ( '' === $field ) {
			return false;
		}

		$credits = self::storedCredits();

		return array_key_exists( $field, $credits ) && empty( $credits[ $field ] );
	}

	public static function currentReason() {
		foreach ( self::reasons() as $reason ) {
			if ( self::isFlagged( $reason ) ) {
				return $reason;
			}
		}

		return '';
	}

	public static function isContactMissing() {
		return self::isFlagged( self::REASON_MISSING );
	}

	public static function isContactResolved() {
		return self::isResolved( self::REASON_MISSING );
	}

	public static function isContactUnreachable() {
		return self::isFlagged( self::REASON_UNREACHABLE );
	}

	private static function fieldFor( $reason ) {
		$fields = self::amsFields();

		return isset( $fields[ $reason ] ) ? $fields[ $reason ] : '';
	}

	private static function storedCredits() {
		$credits = OptionManager::getOr( array(), 'TM', 'Account::credits' );

		return is_array( $credits ) ? $credits : array();
	}

	public static function isDismissed( $reason ) {
		$dismissed = self::dismissedReasons();

		return ! empty( $dismissed[ $reason ] );
	}

	public static function dismiss( $reason ) {
		$dismissed            = self::dismissedReasons();
		$dismissed[ $reason ] = true;

		update_option( self::OPTION, array( 'dismissed' => $dismissed ), 'no' );
	}

	public static function clearDismissal( $reason ) {
		$dismissed = self::dismissedReasons();

		if ( ! isset( $dismissed[ $reason ] ) ) {
			return;
		}

		unset( $dismissed[ $reason ] );

		if ( empty( $dismissed ) ) {
			delete_option( self::OPTION );

			return;
		}

		update_option( self::OPTION, array( 'dismissed' => $dismissed ), 'no' );
	}

	private static function dismissedReasons() {
		$state = get_option( self::OPTION, array() );

		if ( ! is_array( $state ) || empty( $state['dismissed'] ) ) {
			return array();
		}

		if ( ! is_array( $state['dismissed'] ) ) {
			return array( self::REASON_MISSING => true );
		}

		return array_map( 'boolval', $state['dismissed'] );
	}
}
