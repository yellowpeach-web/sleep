<?php

namespace WPML\Translation;

use WPML\TM\Jobs\Authorization\JobAuthorization;

class TranslationGroupAuthorization {

	public static function currentUserCanJoinGroup( $trid, $element_type, $target_language = null, $wpdb = null ) {
		$trid = (int) $trid;
		if ( ! $trid ) {
			return false;
		}

		$wpdb = $wpdb ? $wpdb : $GLOBALS['wpdb'];

		$original = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT element_id, language_code
				 FROM {$wpdb->prefix}icl_translations
				 WHERE trid=%d
					AND element_type=%s
					AND source_language_code IS NULL
				 LIMIT 1",
				$trid,
				$element_type
			)
		);

		if ( ! $original || ! (int) $original->element_id ) {
			return false;
		}

		if ( current_user_can( 'edit_post', (int) $original->element_id ) ) {
			return true;
		}

		if ( ! $target_language || 0 !== strpos( (string) $element_type, 'post_' ) ) {
			return false;
		}

		return self::currentUserCanTranslateInGroup(
			$trid,
			(int) $original->element_id,
			(string) $original->language_code,
			(string) $target_language
		);
	}

	private static function currentUserCanTranslateInGroup( $trid, $original_id, $source_language, $target_language ) {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}

		$job_factory = function_exists( 'wpml_tm_load_job_factory' ) ? wpml_tm_load_job_factory() : null;
		if ( $job_factory && isset( $GLOBALS['iclTranslationManagement'] ) ) {
			$job_id = (int) $job_factory->job_id_by_trid_and_lang( $trid, $target_language );
			if ( $job_id ) {
				return JobAuthorization::currentUserCanTranslateJob( $job_id );
			}
		}

		if ( ! $source_language || $source_language === $target_language ) {
			return false;
		}

		$blog_translators = function_exists( 'wpml_tm_load_blog_translators' ) ? wpml_tm_load_blog_translators() : null;
		if ( ! $blog_translators ) {
			return false;
		}

		return (bool) $blog_translators->is_translator(
			$user_id,
			array(
				'lang_from' => $source_language,
				'lang_to'   => $target_language,
				'post_id'   => $original_id,
			)
		);
	}
}
