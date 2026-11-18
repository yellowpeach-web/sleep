<?php

namespace WPML\PB\Elementor\Hooks;

class DocumentSettingsSync implements \IWPML_Backend_Action, \IWPML_Frontend_Action {

	const PAGE_SETTINGS          = '_elementor_page_settings';
	const POPUP_DISPLAY_SETTINGS = '_elementor_popup_display_settings';
	const KEYS                   = [ self::PAGE_SETTINGS, self::POPUP_DISPLAY_SETTINGS ];

	const COPIED_FIELD_HOOK = 'wpml_after_copy_custom_field';

	const SAFE_CALLBACKS = [
		'added_post_meta'       => 'onMetaWritten',
		'updated_post_meta'     => 'onMetaWritten',
		'deleted_post_meta'     => 'onMetaDeleted',
		self::COPIED_FIELD_HOOK => 'onCustomFieldCopied',
	];

	private static $syncing = false;

	private $syncFrom = 0;

	private $syncKey = '';

	private $copiedTo = [];

	private static $keptOwnValue = [];

	public function add_hooks() {
		add_action( 'added_post_meta', [ $this, 'onMetaWritten' ], 10, 3 );
		add_action( 'updated_post_meta', [ $this, 'onMetaWritten' ], 10, 3 );
		add_action( 'deleted_post_meta', [ $this, 'onMetaDeleted' ], 10, 4 );
		add_filter( 'wpml_sync_custom_fields_batch_writer_is_safe_callback', [ $this, 'declareOwnCallbacksSafe' ], 10, 3 );
		add_filter( 'wpml_custom_fields_to_sync_on_post_save', [ $this, 'keepNativeEditorSettingsOnSave' ], 10, 2 );
	}

	public function keepNativeEditorSettingsOnSave( $fields, $postId ) {
		if ( ! is_array( $fields ) || ! array_intersect( self::KEYS, $fields ) ) {
			return $fields;
		}

		if ( ! self::isNativeEditorTranslation( (int) $postId ) ) {
			return $fields;
		}

		return array_values( array_diff( $fields, self::KEYS ) );
	}

	public static function ownValueOfNativeEditorTranslation( $postIdTo, $metaKey ) {
		$postIdTo = (int) $postIdTo;
		if ( $postIdTo <= 0 || ! in_array( (string) $metaKey, self::KEYS, true ) ) {
			return [];
		}

		if ( ! self::isNativeEditorTranslation( $postIdTo ) ) {
			return [];
		}

		$rows = get_post_meta( $postIdTo, (string) $metaKey, false );
		if ( ! is_array( $rows ) || 1 !== count( $rows ) ) {
			return [];
		}

		self::$keptOwnValue[ $postIdTo ] = true;

		return array_values( $rows );
	}

	public function onMetaWritten( $metaId, $postId, $metaKey ) {
		$this->maybeSync( (int) $postId, (string) $metaKey );
	}

	public function onMetaDeleted( $metaIds, $postId, $metaKey, $metaValue ) {
		$this->maybeSync( (int) $postId, (string) $metaKey );
	}

	public function declareOwnCallbacksSafe( $isSafe, $hookName, $callback ) {
		if (
			isset( self::SAFE_CALLBACKS[ $hookName ] )
			&& is_array( $callback )
			&& 2 === count( $callback )
			&& $this === $callback[0]
			&& self::SAFE_CALLBACKS[ $hookName ] === $callback[1]
		) {
			return true;
		}

		return $isSafe;
	}

	private function maybeSync( $postId, $metaKey ) {
		if ( self::$syncing || $postId <= 0 || ! in_array( $metaKey, self::KEYS, true ) ) {
			return;
		}

		if ( wp_is_post_revision( $postId ) || wp_is_post_autosave( $postId ) ) {
			return;
		}

		if ( ! $this->isOriginal( $postId ) ) {
			return;
		}

		self::$syncing      = true;
		self::$keptOwnValue = [];
		$this->syncFrom     = $postId;
		$this->syncKey      = $metaKey;
		$this->copiedTo     = [];
		add_action( self::COPIED_FIELD_HOOK, [ $this, 'onCustomFieldCopied' ], 10, 4 );
		DocumentSettingsIds::enable();
		try {
			do_action( 'wpml_sync_custom_field', $postId, $metaKey );
		} finally {
			DocumentSettingsIds::disable();
			remove_action( self::COPIED_FIELD_HOOK, [ $this, 'onCustomFieldCopied' ], 10 );
			self::$syncing = false;
		}

		foreach ( $this->takeCopiedTranslations() as $translationId ) {
			Cache::deletePostCss( $translationId );
		}
	}

	private function takeCopiedTranslations() {
		$copiedTo       = array_keys( $this->copiedTo );
		$this->copiedTo = [];

		return $copiedTo;
	}

	public function onCustomFieldCopied( $postIdFrom, $postIdTo, $metaKey, $valuesAfter ) {
		$postIdTo = (int) $postIdTo;
		if (
			self::$syncing
			&& (int) $postIdFrom === $this->syncFrom
			&& (string) $metaKey === $this->syncKey
			&& $postIdTo > 0
			&& $postIdTo !== $this->syncFrom
			&& ! isset( self::$keptOwnValue[ $postIdTo ] )
		) {
			$this->copiedTo[ $postIdTo ] = true;
		}
	}

	private function isOriginal( $postId ) {
		$details = self::languageDetails( $postId );

		return is_object( $details ) && empty( $details->source_language_code );
	}

	private static function isNativeEditorTranslation( $postId ) {
		if ( $postId <= 0 ) {
			return false;
		}

		$details = self::languageDetails( $postId );
		if ( ! is_object( $details ) || empty( $details->source_language_code ) ) {
			return false;
		}

		return \WPML_PB_Last_Translation_Edit_Mode::is_native_editor( $postId )
			|| (bool) apply_filters( 'wpml_pb_is_editing_translation_with_native_editor', false, $postId );
	}

	private static function languageDetails( $postId ) {
		$postType = get_post_type( $postId );
		if ( ! is_string( $postType ) || '' === $postType ) {
			return null;
		}

		$details = apply_filters(
			'wpml_element_language_details',
			null,
			[
				'element_id'   => $postId,
				'element_type' => 'post_' . $postType,
			]
		);

		return is_object( $details ) ? $details : null;
	}
}
