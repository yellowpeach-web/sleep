<?php

namespace WPML\PB\Elementor\AutoConfig\Processors;

class ControlNameFilter {

	private const EXCLUDE_NAMES = [
		'email_to',
		'email_from',
		'email_reply_to',
		'email_to_cc',
		'email_to_bcc',
		'email_to_2',
		'email_from_2',
		'email_reply_to_2',
		'email_to_cc_2',
		'email_to_bcc_2',
	];

	private static function getExcludeNeedles() {
		return [ '_css', '_js', '_id', 'input-id' ];
	}

	public static function shouldExclude( $controlName ) {
		if ( in_array( $controlName, self::EXCLUDE_NAMES, true ) ) {
			return true;
		}

		$needles = self::getExcludeNeedles();

		foreach ( $needles as $needle ) {
			if ( strpos( $controlName, $needle ) !== false ) {
				return true;
			}
		}

		return false;
	}
}
