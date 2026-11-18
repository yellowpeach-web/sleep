<?php

namespace WPML\TranslationRoles\Service;

use WPML\LIB\WP\User;
use function WPML\Container\make;

class TranslatorCapability {

	const PAIRS_SET = 'set';

	const PAIRS_ADD = 'add';

	const PAIRS_ALL = 'all';

	public function grant( $userId, array $pairs, $pairsMode ) {
		if ( ! in_array( $pairsMode, [ self::PAIRS_SET, self::PAIRS_ADD, self::PAIRS_ALL ], true ) ) {
			throw new \InvalidArgumentException( 'Unknown language pairs mode; expected one of the PAIRS_* constants.' );
		}

		$user = User::get( $userId );
		if ( ! $user->exists() ) {
			return;
		}

		$user->add_cap( User::CAP_TRANSLATE );

		$pairRecords = make( \WPML_Language_Pair_Records::class );
		if ( self::PAIRS_ALL === $pairsMode ) {
			$pairRecords->store( $user->ID, \WPML_All_Language_Pairs::get() );
			User::updateMeta( $user->ID, \WPML_TM_Wizard_Options::ONLY_I_USER_META, true );
		} elseif ( self::PAIRS_ADD === $pairsMode ) {
			$pairRecords->store( $user->ID, self::addPairs( $pairRecords->get( $user->ID ), $pairs ) );
		} else {
			$pairRecords->store( $user->ID, $pairs );
			User::deleteMeta( $user->ID, \WPML_TM_Wizard_Options::ONLY_I_USER_META );
		}

		do_action( 'wpml_tm_add_translation_role', $user, User::CAP_TRANSLATE );
		\WPML_Translator_Records::forget_user_capability_memo( $user->ID );
		do_action( 'wpml_update_translator', $user->ID );
	}

	public function revoke( $userId ) {
		$user = User::get( $userId );
		if ( ! $user->exists() ) {
			return;
		}

		make( \TranslationManagement::class )->resign_translator_from_unfinished_jobs( $user );
		$user->remove_cap( User::CAP_TRANSLATE );
		make( \WPML_Language_Pair_Records::class )->remove_all( $user->ID );
		User::deleteMeta( $user->ID, \WPML_TM_Wizard_Options::ONLY_I_USER_META );

		do_action( 'wpml_tm_remove_translation_role', $user, User::CAP_TRANSLATE );
		\WPML_Translator_Records::forget_user_capability_memo( $user->ID );
		do_action( 'wpml_tm_ate_synchronize_translators' );
	}

	private static function addPairs( array $stored, array $pairs ) {
		foreach ( $pairs as $source => $targets ) {
			$current           = isset( $stored[ $source ] ) ? (array) $stored[ $source ] : [];
			$stored[ $source ] = array_values( array_unique( array_merge( $current, (array) $targets ) ) );
		}

		return $stored;
	}
}
