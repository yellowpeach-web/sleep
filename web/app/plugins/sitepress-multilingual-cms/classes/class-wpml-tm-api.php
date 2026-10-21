<?php

require_once __DIR__ . '/../inc/constants-since-5-0.php';

use WPML\TM\ATE\Review\ReviewStatus;
use WPML\FP\Obj;

class WPML_TM_API {

	private $TranslationManagement;

	private $blog_translators;
	private $translation_statuses;

	public function __construct( &$blog_translators, &$TranslationManagement ) {
		$this->blog_translators      = &$blog_translators;
		$this->TranslationManagement = &$TranslationManagement;

		$this->translation_statuses = [
			/* translators: Status of a piece of content: it has no translation in that language yet. */
			ICL_TM_NOT_TRANSLATED         => __( 'Not translated', 'sitepress' ),
			ICL_TM_WAITING_FOR_TRANSLATOR => __( 'Waiting for translator', 'sitepress' ),
			/* translators: Status of a translation: it is being made right now. */
			ICL_TM_IN_PROGRESS            => __( 'In progress', 'sitepress' ),
			/* translators: Status of a translation: it is a copy of the original that WPML keeps in step, not a translation of its own. Noun. */
			ICL_TM_DUPLICATE              => _x( 'Duplicate', 'status of a translation', 'sitepress' ),
			/* translators: Status of a translation: it is finished. Adjective, not an instruction to finish it. */
			ICL_TM_COMPLETE               => __( 'Complete', 'sitepress' ),
			/* translators: Status of a translation: the original changed since, so the translation has to be gone over again. Written in lower case as the code shows it. */
			ICL_TM_NEEDS_UPDATE           => __( 'needs update', 'sitepress' ),
			ICL_TM_ATE_NEEDS_RETRY        => __( 'In progress (connecting)', 'sitepress' ),
			ICL_TM_ATE_UNSOLVABLE         => __( 'Failed - needs attention', 'sitepress' ),
		];
	}

	public function get_translation_status_label( $status ) {
		return Obj::propOr( null, $status, $this->translation_statuses );
	}

	public function init_hooks() {
		add_filter( 'wpml_is_translator', array( $this, 'is_translator_filter' ), 10, 3 );
		add_filter( 'wpml_translator_languages_pairs', array( $this, 'translator_languages_pairs_filter' ), 10, 2 );
		add_action( 'wpml_edit_translator', array( $this, 'edit_translator_action' ), 10, 2 );
	}

	public function is_translator_filter( $default, $user, $args ) {
		$result  = $default;
		$user_id = $this->get_user_id( $user );
		if ( is_numeric( $user_id ) ) {
			$result = $this->blog_translators->is_translator( $user_id, $args );
		}

		return $result;
	}

	public function edit_translator_action( $user, $language_pairs ) {
		$user_id = $this->get_user_id( $user );
		if ( is_numeric( $user_id ) ) {
			$this->edit_translator( $user_id, $language_pairs );
		}
	}

	private function edit_translator( $user_id, $language_pairs ) {
		global $wpdb;

		$user = new WP_User( $user_id );

		if ( empty( $language_pairs ) ) {
			$user->remove_cap( \WPML\LIB\WP\User::CAP_TRANSLATE );
		} elseif ( ! $user->has_cap( \WPML\LIB\WP\User::CAP_TRANSLATE ) ) {
			$user->add_cap( \WPML\LIB\WP\User::CAP_TRANSLATE );
		}

		$language_pair_records = new WPML_Language_Pair_Records( $wpdb, new WPML_Language_Records( $wpdb ) );
		$language_pair_records->store( $user_id, $language_pairs );
	}

	public function translator_languages_pairs_filter( $default, $user ) {
		$result  = $default;
		$user_id = $this->get_user_id( $user );
		if ( is_numeric( $user_id ) ) {
			if ( $this->blog_translators->is_translator( $user_id ) ) {
				$result = $this->blog_translators->get_language_pairs( $user_id );
			}
		}

		return $result;
	}

	private function get_user_id( $user ) {
		$user_id = $user;

		if ( is_a( $user, 'WP_User' ) ) {
			$user_id = $user->ID;

			return $user_id;
		}

		return $user_id;
	}

}
