<?php

namespace WPML\ContentDeletion\Endpoint;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use WPML\ContentDeletion\DialogAnswer;
use WPML\ContentDeletion\PromotePick;
use WPML\ContentDeletion\SetJobs;
use WPML\ContentDeletion\Settings;
use WPML\FP\Either;

class PreflightDeletion implements IHandler {

	const TYPE_POST = 'post';

	const TYPE_TERM = 'term';

	const INTENT_TRASH  = 'trash';
	const INTENT_DELETE = 'delete';

	public function run( Collection $data ) {
		$type = (string) $data->get( 'type', self::TYPE_POST );

		if ( self::TYPE_TERM === $type ) {
			return $this->term( $data );
		}

		if ( self::TYPE_POST !== $type ) {
			return Either::left( array( 'error' => 'unsupported_type' ) );
		}

		return $this->post( $data );
	}

	private function post( Collection $data ) {
		$id     = (int) $data->get( 'id', 0 );
		$intent = (string) $data->get( 'intent', self::INTENT_TRASH );

		if ( $id <= 0 ) {
			return Either::left( array( 'error' => 'invalid_id' ) );
		}

		if ( ! current_user_can( 'delete_post', $id ) ) {
			return Either::left( array( 'error' => 'forbidden' ) );
		}

		$translations = $this->translations();

		if ( ! $translations ) {
			return Either::left( array( 'error' => 'translations_unavailable' ) );
		}

		$post_type = get_post_type( $id );

		if ( ! $post_type ) {
			return Either::left( array( 'error' => 'not_found' ) );
		}

		$group       = array_map( 'intval', (array) $translations->get_element_translations( $id, false, false ) );
		$trid        = (int) $translations->get_element_trid( $id );
		$source_lang = $translations->get_source_lang_code( $id );
		$is_original = empty( $source_lang );
		$original_id = $is_original ? $id : (int) $translations->get_original_element( $id, true );

		$settings  = new Settings();
		$effective = $settings->effectiveFor( Settings::postKey( $post_type ) );
		$column    = $is_original ? 'original' : 'translation';

		$jobs = SetJobs::collect( $trid );

		$promote = $this->promoteDetails( $group, $id );

		return Either::right(
			array_merge(
				array(
					'id'                => $id,
					'intent'            => self::INTENT_DELETE === $intent ? self::INTENT_DELETE : self::INTENT_TRASH,
					'postType'          => $post_type,
					'isOriginal'        => $is_original,
					'setSize'           => count( $group ),
					'languages'         => $this->languages( $group ),
					'originalId'        => $original_id ? $original_id : null,
					'originalEditUrl'   => $original_id ? $this->editUrl( $original_id ) : null,
					'originalTrashUrl'  => $original_id ? $this->deleteUrl( $original_id, false ) : null,
					'originalDeleteUrl' => $original_id ? $this->deleteUrl( $original_id, true ) : null,
					'canDeleteOriginal' => $original_id > 0 && current_user_can( 'delete_post', $original_id ),
					'selfTrashUrl'      => $this->deleteUrl( $id, false ),
					'selfDeleteUrl'     => $this->deleteUrl( $id, true ),
					'promoteCandidate'  => $promote['candidate'],
					'promoteByOrder'    => $promote['byOrder'],
					'inProgressJobs'    => array(
						'count'        => count( $jobs['queued'] ) + count( $jobs['in_progress'] ),
						'inProgress'   => count( $jobs['in_progress'] ),
						'chargedWords' => (int) $jobs['charged_words'],
					),
					'hasStatuses'       => 'attachment' !== $post_type,
					'isDuplicate'       => $this->isDuplicate( $id ),
					'remembered'        => $effective,
					'askNeeded'         => count( $group ) > 1 && Settings::ASK === $effective[ $column ],
				),
				$this->signature()
			)
		);
	}

	private function signature() {
		return array(
			'scopeField'   => DialogAnswer::FIELD_SCOPE,
			'nonceField'   => DialogAnswer::FIELD_NONCE,
			'promoteField' => DialogAnswer::FIELD_PROMOTE,
			'scopeNonce'   => wp_create_nonce( DialogAnswer::NONCE_ACTION ),
		);
	}

	private function isDuplicate( $post_id ) {
		if ( ! function_exists( 'get_post_meta' ) ) {
			return false;
		}

		return (int) get_post_meta( (int) $post_id, '_icl_lang_duplicate_of', true ) > 0;
	}

	private function term( Collection $data ) {
		$term_id  = (int) $data->get( 'id', 0 );
		$taxonomy = (string) $data->get( 'taxonomy', '' );

		if ( $term_id <= 0 || '' === $taxonomy ) {
			return Either::left( array( 'error' => 'invalid_id' ) );
		}

		if ( ! taxonomy_exists( $taxonomy ) ) {
			return Either::left( array( 'error' => 'not_found' ) );
		}

		if ( ! current_user_can( 'delete_term', $term_id ) ) {
			return Either::left( array( 'error' => 'forbidden' ) );
		}

		$translations = $this->termTranslations();

		if ( ! $translations ) {
			return Either::left( array( 'error' => 'translations_unavailable' ) );
		}

		$tt_id = (int) $translations->adjust_ttid_for_term_id( $term_id );
		$trid  = (int) $translations->get_element_trid( $tt_id );

		if ( ! $trid ) {
			return Either::left( array( 'error' => 'not_found' ) );
		}

		$members = $this->termMembers( $trid, $taxonomy );

		if ( ! isset( $members[ $term_id ] ) ) {
			return Either::left( array( 'error' => 'not_found' ) );
		}

		$names       = $this->namesFor( array_map( 'strval', array_column( $members, 'code' ) ) );
		$is_original = '' === (string) $members[ $term_id ]['source'];
		$original_id = 0;
		$group       = array();
		$languages   = array();

		foreach ( $members as $member_term_id => $member ) {
			$group[ $member['code'] ] = (int) $member_term_id;

			$languages[] = array(
				'code'   => $member['code'],
				'name'   => isset( $names[ $member['code'] ] ) ? $names[ $member['code'] ] : $member['code'],
				'id'     => (int) $member_term_id,
				'status' => '',
			);

			if ( '' === (string) $member['source'] ) {
				$original_id = (int) $member_term_id;
			}
		}

		$settings  = new Settings();
		$effective = $settings->effectiveFor( Settings::termKey( $taxonomy ) );
		$column    = $is_original ? 'original' : 'translation';

		$promote = $this->promoteDetails( $group, $term_id );

		return Either::right(
			array_merge(
				array(
					'id'                => $term_id,
					'intent'            => self::INTENT_DELETE,
					'postType'          => $taxonomy,
					'isOriginal'        => $is_original,
					'setSize'           => count( $languages ),
					'languages'         => $languages,
					'originalId'        => $original_id ? $original_id : null,
					'originalEditUrl'   => $original_id ? $this->termEditUrl( $original_id, $taxonomy ) : null,
					'originalTrashUrl'  => $original_id ? $this->termDeleteUrl( $original_id, $taxonomy ) : null,
					'originalDeleteUrl' => $original_id ? $this->termDeleteUrl( $original_id, $taxonomy ) : null,
					'canDeleteOriginal' => $original_id > 0 && current_user_can( 'delete_term', $original_id ),
					'selfTrashUrl'      => $this->termDeleteUrl( $term_id, $taxonomy ),
					'selfDeleteUrl'     => $this->termDeleteUrl( $term_id, $taxonomy ),
					'promoteCandidate'  => $promote['candidate'],
					'promoteByOrder'    => $promote['byOrder'],
					'inProgressJobs'    => array(
						'count'        => 0,
						'inProgress'   => 0,
						'chargedWords' => 0,
					),
					'hasStatuses'       => false,
					'isDuplicate'       => false,
					'remembered'        => $effective,
					'askNeeded'         => count( $languages ) > 1 && Settings::ASK === $effective[ $column ],
				),
				$this->signature()
			)
		);
	}

	private function termMembers( $trid, $taxonomy ) {
		global $sitepress;

		if ( ! is_object( $sitepress ) || ! method_exists( $sitepress, 'get_element_translations' ) ) {
			return array();
		}

		$members = array();

		foreach ( (array) $sitepress->get_element_translations( $trid, 'tax_' . $taxonomy, false ) as $translation ) {
			$translation = (object) $translation;

			$member_term_id = isset( $translation->term_id ) ? (int) $translation->term_id : 0;

			if ( ! $member_term_id ) {
				continue;
			}

			$members[ $member_term_id ] = array(
				'code'   => isset( $translation->language_code ) ? (string) $translation->language_code : '',
				'source' => isset( $translation->source_language_code ) ? (string) $translation->source_language_code : '',
			);
		}

		return $members;
	}

	private function termDeleteUrl( $term_id, $taxonomy ) {
		$term_id = (int) $term_id;

		if ( ! $term_id || ! function_exists( 'admin_url' ) || ! function_exists( 'wp_nonce_url' ) ) {
			return null;
		}

		if ( 'nav_menu' === $taxonomy ) {
			return (string) wp_nonce_url(
				admin_url( 'nav-menus.php?action=delete&menu=' . $term_id ),
				'delete-nav_menu-' . $term_id
			);
		}

		return (string) wp_nonce_url(
			admin_url( 'edit-tags.php?action=delete&taxonomy=' . rawurlencode( $taxonomy ) . '&tag_ID=' . $term_id ),
			'delete-tag_' . $term_id
		);
	}

	private function termEditUrl( $term_id, $taxonomy ) {
		if ( ! function_exists( 'get_edit_term_link' ) ) {
			return null;
		}

		$url = get_edit_term_link( (int) $term_id, $taxonomy );

		return $url ? (string) $url : null;
	}

	private function termTranslations() {
		global $wpml_term_translations;

		return is_object( $wpml_term_translations )
			&& method_exists( $wpml_term_translations, 'adjust_ttid_for_term_id' )
			&& method_exists( $wpml_term_translations, 'get_element_trid' )
			? $wpml_term_translations
			: null;
	}

	private function languages( array $group ) {
		$names     = $this->namesFor( array_map( 'strval', array_keys( $group ) ) );
		$languages = array();

		foreach ( $group as $code => $element_id ) {
			$code = (string) $code;

			$languages[] = array(
				'code'   => $code,
				'name'   => isset( $names[ $code ] ) ? $names[ $code ] : $code,
				'id'     => (int) $element_id,
				'status' => (string) get_post_status( (int) $element_id ),
			);
		}

		return $languages;
	}

	private function promoteDetails( array $group, $deleted_id ) {
		$pick = PromotePick::pickWithArm( $this->languageOrder(), $group, $deleted_id );
		$code = $pick['code'];

		if ( null === $code ) {
			return array(
				'candidate' => null,
				'byOrder'   => false,
			);
		}

		$names = $this->namesFor( array( $code ) );

		return array(
			'candidate' => array(
				'code' => $code,
				'name' => isset( $names[ $code ] ) ? $names[ $code ] : $code,
			),
			'byOrder'   => $pick['byOrder'],
		);
	}

	private function languageOrder() {
		global $sitepress;

		if ( ! is_object( $sitepress ) || ! method_exists( $sitepress, 'get_setting' ) ) {
			return array();
		}

		$order = $sitepress->get_setting( 'languages_order' );

		return is_array( $order ) ? array_map( 'strval', $order ) : array();
	}

	private function namesFor( array $codes ) {
		$names   = $this->languageNames();
		$missing = array();

		foreach ( $codes as $code ) {
			if ( '' !== $code && ! isset( $names[ $code ] ) ) {
				$missing[] = $code;
			}
		}

		if ( $missing ) {
			$names += \WPML\LanguageEditor\RemovedLanguages\DisplayNames::forCodes( $missing );
		}

		return $names;
	}

	private function languageNames() {
		global $sitepress;

		if ( ! is_object( $sitepress ) || ! method_exists( $sitepress, 'get_active_languages' ) ) {
			return array();
		}

		$names = array();

		foreach ( (array) $sitepress->get_active_languages() as $code => $language ) {
			$language = (array) $language;

			$names[ (string) $code ] = isset( $language['display_name'] ) ? (string) $language['display_name'] : (string) $code;
		}

		return $names;
	}

	private function editUrl( $post_id ) {
		$url = get_edit_post_link( $post_id, 'raw' );

		return $url ? (string) $url : null;
	}

	private function deleteUrl( $post_id, $force_delete ) {
		$url = get_delete_post_link( $post_id, '', $force_delete );

		return $url ? (string) $url : null;
	}

	private function translations() {
		global $wpml_post_translations;

		return is_object( $wpml_post_translations ) && method_exists( $wpml_post_translations, 'get_element_translations' )
			? $wpml_post_translations
			: null;
	}
}
