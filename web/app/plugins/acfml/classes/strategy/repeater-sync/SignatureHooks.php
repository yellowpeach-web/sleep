<?php

namespace ACFML\Repeater\Sync;

use ACFML\Repeater\Shuffle\Post;
use ACFML\Repeater\Shuffle\Rows;

class SignatureHooks implements \IWPML_Backend_Action, \IWPML_Frontend_Action, \IWPML_DIC_Action {

	private $shuffled;

	private $decided = [];

	public function __construct( Post $shuffled ) {
		$this->shuffled = $shuffled;
	}

	public function add_hooks() {
		add_filter( 'wpml_custom_field_values_for_post_signature', [ $this, 'dropRows' ], 10, 2 );

		add_action( 'added_post_meta', [ $this, 'forgetPostOfMeta' ], 10, 2 );
		add_action( 'updated_post_meta', [ $this, 'forgetPostOfMeta' ], 10, 2 );
		add_action( 'deleted_post_meta', [ $this, 'forgetPostOfMeta' ], 10, 2 );
		add_action( 'acf/save_post', [ $this, 'forgetPost' ], 0 );
		add_action( 'add_option_' . CheckboxOption::SYNCHRONISE_WP_OPTION_NAME, [ $this, 'forgetAll' ] );
		add_action( 'update_option_' . CheckboxOption::SYNCHRONISE_WP_OPTION_NAME, [ $this, 'forgetAll' ] );
		add_action( 'delete_option_' . CheckboxOption::SYNCHRONISE_WP_OPTION_NAME, [ $this, 'forgetAll' ] );
		add_action( 'wpml_translation_update', [ $this, 'forgetAll' ] );
	}

	public function dropRows( $customFields, $postId = 0 ) {
		if ( ! is_array( $customFields ) || ! $customFields || ! $postId ) {
			return $customFields;
		}

		$id = (int) $postId;

		if ( ! isset( $this->decided[ $id ] ) ) {
			$this->decided[ $id ] = [
				'wrappers' => Condition::isActiveFor( $this->shuffled, $postId ) ? array_keys( Rows::read( $postId ) ) : [],
				'keys'     => [],
				'dropped'  => [],
			];
		}

		$decided = &$this->decided[ $id ];

		if ( ! $decided['wrappers'] ) {
			return $customFields;
		}

		foreach ( array_keys( array_diff_key( $customFields, $decided['keys'] ) ) as $key ) {
			$belongs                 = self::belongsToAWrapper( (string) $key, $decided['wrappers'] );
			$decided['keys'][ $key ] = $belongs;

			if ( $belongs ) {
				$decided['dropped'][ $key ] = true;
			}
		}

		return $decided['dropped'] ? array_diff_key( $customFields, $decided['dropped'] ) : $customFields;
	}

	public function forgetPostOfMeta( $metaId, $objectId = 0 ) {
		if ( $objectId ) {
			$this->forgetPost( $objectId );
		} else {
			$this->forgetAll();
		}
	}

	public function forgetPost( $postId ) {
		unset( $this->decided[ (int) $postId ] );
	}

	public function forgetAll() {
		$this->decided = [];
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
