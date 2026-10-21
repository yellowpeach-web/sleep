<?php

namespace WPML\User;

use WPML\FP\Fns;
use WPML\FP\Lst;
use WPML\User\LanguagePairs\ILanguagePairs;
use function WPML\FP\pipe;

class UsersByCapsRepository {

	private $wpdb;

	private $languagePairs;

	public function __construct( \wpdb $wpdb, ILanguagePairs $languagePairs ) {
		$this->wpdb          = $wpdb;
		$this->languagePairs = $languagePairs;
	}

	public function get( array $ownedCaps, array $excludeCaps = [] ) {
		if ( ! $ownedCaps ) {
			return [];
		}

		$wpdb       = $this->wpdb;
		$conditions = array_fill( 0, count( $ownedCaps ), 'meta_value LIKE %s' );
		$values     = array_map(
			function( $cap ) use ( $wpdb ) {
				return '%' . $wpdb->esc_like( $cap ) . '%';
			},
			$ownedCaps
		);

		if ( $excludeCaps ) {
			$conditions[] = '( ' . implode( ' AND ', array_fill( 0, count( $excludeCaps ), 'meta_value NOT LIKE %s' ) ) . ' )';
			$values       = array_merge(
				$values,
				array_map(
					function( $cap ) use ( $wpdb ) {
						return '%' . $wpdb->esc_like( $cap ) . '%';
					},
					$excludeCaps
				)
			);
		}

		if ( $excludeCaps ) {
			$userIds = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT user_id
					FROM {$wpdb->usermeta}
					WHERE meta_key = %s AND ( "
					. implode( ' OR ', array_fill( 0, count( $ownedCaps ), 'meta_value LIKE %s' ) )
					. ' ) AND ( '
					. implode( ' AND ', array_fill( 0, count( $excludeCaps ), 'meta_value NOT LIKE %s' ) )
					. ' )',
					array_merge( array( $wpdb->prefix . 'capabilities' ), $values )
				)
			);
		} else {
			$userIds = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT user_id
					FROM {$wpdb->usermeta}
					WHERE meta_key = %s AND ( "
					. implode( ' OR ', array_fill( 0, count( $ownedCaps ), 'meta_value LIKE %s' ) )
					. ' )',
					array_merge( array( $wpdb->prefix . 'capabilities' ), $values )
				)
			);
		}

		$buildUsers = pipe(
			Fns::map( 'get_userdata' ),
			Fns::filter( Fns::identity() ),
			Fns::map( function ( \WP_User $userData ) {
				return (object) [
					'ID'           => $userData->ID,
					'full_name'    => trim( $userData->first_name . ' ' . $userData->last_name ),
					'user_login'   => $userData->user_login,
					'user_email'   => $userData->user_email,
					'display_name' => $userData->display_name,
					'roles'        => $userData->roles
				];
			} ),
			Fns::map( function ( $user ) {
				$user->language_pairs = $this->languagePairs->get( $user->ID );

				return $user;
			} )
		);

		return $buildUsers( $userIds );
	}

}
