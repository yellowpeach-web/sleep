<?php

namespace ACFML\Repeater\Sync;

use ACFML\Repeater\Shuffle\Post;
use ACFML\Repeater\Shuffle\Rows;

class SignatureHooks implements \IWPML_Backend_Action, \IWPML_Frontend_Action, \IWPML_DIC_Action {

	private $shuffled;

	public function __construct( Post $shuffled ) {
		$this->shuffled = $shuffled;
	}

	public function add_hooks() {
		add_filter( 'wpml_custom_field_values_for_post_signature', [ $this, 'dropRows' ], 10, 2 );
	}

	public function dropRows( $customFields, $postId = 0 ) {
		if ( ! is_array( $customFields ) || ! $customFields || ! $postId ) {
			return $customFields;
		}

		if ( ! Condition::isActiveFor( $this->shuffled, $postId ) ) {
			return $customFields;
		}

		$wrappers = array_keys( Rows::read( $postId ) );

		if ( ! $wrappers ) {
			return $customFields;
		}

		foreach ( array_keys( $customFields ) as $key ) {
			if ( self::belongsToAWrapper( (string) $key, $wrappers ) ) {
				unset( $customFields[ $key ] );
			}
		}

		return $customFields;
	}

	private static function belongsToAWrapper( $key, array $wrappers ) {
		foreach ( $wrappers as $wrapper ) {
			if ( preg_match( '/^_?' . preg_quote( (string) $wrapper, '/' ) . '(_\d+_|$)/', $key ) ) {
				return true;
			}
		}

		return false;
	}
}
