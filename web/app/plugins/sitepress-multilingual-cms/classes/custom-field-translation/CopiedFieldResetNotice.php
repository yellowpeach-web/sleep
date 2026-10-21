<?php

namespace WPML\CustomFieldTranslation;

class CopiedFieldResetNotice implements \IWPML_Backend_Action, \IWPML_DIC_Action {

	const META_KEY = 'wpml_copied_field_resets';

	const MAX_REMEMBERED_SAVES = 50;

	const DISPLAY_NAME_FILTER = 'wpml_copied_field_reset_display_name';

	private $translationManagement;

	public function __construct( \TranslationManagement $translationManagement ) {
		$this->translationManagement = $translationManagement;
	}

	public function add_hooks() {
		add_action( 'admin_notices', [ $this, 'renderOnAdminScreen' ] );
		add_action( 'enqueue_block_editor_assets', [ $this, 'renderInBlockEditor' ] );
	}

	public static function remember( $userId, $translationId, $originalId, array $resetFields ) {
		$save = [
			'translationId' => (int) $translationId,
			'originalId'    => (int) $originalId,
			'fields'        => array_values( $resetFields ),
			'savedAt'       => time(),
		];
		add_user_meta( $userId, self::getMetaKeyOfCurrentSite(), wp_slash( $save ) );

		self::forgetSavesBeyondTheLimit( $userId );
	}

	public function renderOnAdminScreen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && $screen->is_block_editor() ) {
			return;
		}

		$resets = $this->takeResetsOfCurrentUser();
		if ( ! $resets ) {
			return;
		}

		$notice = \WPML_Notice::make( 'copied-field-reset', esc_html( $this->getText( $resets ) ) );
		$notice->set_css_class_types( 'warning' );
		$editOriginalAction = $this->getEditOriginalAction( $resets );
		if ( $editOriginalAction ) {
			$notice->add_action( new \WPML_Notice_Action( $editOriginalAction['label'], $editOriginalAction['url'] ) );
		}

		( new \WPML_Notice_Render() )->render( $notice );
	}

	public function renderInBlockEditor() {
		$resets = $this->takeResetsOfCurrentUser();
		if ( ! $resets ) {
			return;
		}

		$editOriginalAction = $this->getEditOriginalAction( $resets );
		$actions            = $editOriginalAction ? [ $editOriginalAction ] : [];

		wp_add_inline_script(
			'wp-notices',
			sprintf(
				'wp.data.dispatch( "core/notices" ).createWarningNotice( %s, { id: "wpml-copied-field-reset", actions: %s } );',
				wp_json_encode( $this->getText( $resets ) ),
				wp_json_encode( $actions )
			)
		);
	}

	private static function getMetaKeyOfCurrentSite() {
		global $wpdb;

		return $wpdb->get_blog_prefix() . self::META_KEY;
	}

	private static function forgetSavesBeyondTheLimit( $userId ) {
		$saves       = self::getRememberedSaves( $userId );
		$oldestSaves = array_slice( $saves, 0, max( 0, count( $saves ) - self::MAX_REMEMBERED_SAVES ) );

		foreach ( $oldestSaves as $save ) {
			self::forgetSave( $userId, $save );
		}
	}

	private static function forgetSave( $userId, $save ) {
		delete_user_meta( $userId, self::getMetaKeyOfCurrentSite(), wp_slash( $save ) );
	}

	private static function getRememberedSaves( $userId ) {
		$saves = get_user_meta( $userId, self::getMetaKeyOfCurrentSite(), false );

		return is_array( $saves ) ? array_values( $saves ) : [];
	}

	private static function isRecentSave( $save ) {
		return is_array( $save )
			&& ! empty( $save['translationId'] )
			&& isset( $save['originalId'], $save['fields'], $save['savedAt'] )
			&& is_array( $save['fields'] )
			&& (int) $save['savedAt'] > time() - DAY_IN_SECONDS;
	}

	private function takeResetsOfCurrentUser() {
		$userId = get_current_user_id();
		if ( ! $userId ) {
			return null;
		}

		$resets = [ 'translations' => [], 'fields' => [] ];
		foreach ( self::getRememberedSaves( $userId ) as $save ) {
			self::forgetSave( $userId, $save );
			if ( ! self::isRecentSave( $save ) ) {
				continue;
			}

			$translationId                            = (int) $save['translationId'];
			$resets['translations'][ $translationId ] = (int) $save['originalId'];
			foreach ( $save['fields'] as $field ) {
				$resets['fields'][ $translationId ][ $field ] = $field;
			}
		}

		return $resets['translations'] ? $resets : null;
	}

	private function getDisplayedFieldNames( array $resets ) {
		$displayedNames = [];
		foreach ( $resets['fields'] as $translationId => $metaKeys ) {
			foreach ( $metaKeys as $metaKey ) {
				$displayedName                    = $this->getDisplayedFieldName( $metaKey, $translationId );
				$displayedNames[ $displayedName ] = $displayedName;
			}
		}

		return array_values( $displayedNames );
	}

	private function getDisplayedFieldName( $metaKey, $translationId ) {
		$configuredLabel = (string) $this->translationManagement->settings_factory()->post_meta_setting( $metaKey )->get_editor_label();

		$displayedName = apply_filters( self::DISPLAY_NAME_FILTER, '' !== $configuredLabel ? $configuredLabel : $metaKey, $metaKey, $translationId );

		return is_string( $displayedName ) && '' !== $displayedName ? $displayedName : $metaKey;
	}

	private function getText( array $resets ) {
		$fieldNames       = $this->getDisplayedFieldNames( $resets );
		$fieldCount       = count( $fieldNames );
		$translationCount = count( $resets['translations'] );
		$fieldList        = wp_sprintf_l( '%l', $fieldNames );

		if ( 1 === $translationCount ) {
			$translationTitle = html_entity_decode( get_the_title( (int) key( $resets['translations'] ) ), ENT_QUOTES, 'UTF-8' );
			$whatHappened     = sprintf(
				/* translators: Notice on an admin screen after a translation was saved. %1$s: the names of the custom fields, as a list like "price and color". %2$s: the title of the translated post. */
				_n(
					'WPML changed the %1$s value saved on “%2$s” to match the original.',
					'WPML changed the %1$s values saved on “%2$s” to match the original.',
					$fieldCount,
					'sitepress'
				),
				$fieldList,
				$translationTitle
			);
		} else {
			$whatHappened = sprintf(
				/* translators: Notice on an admin screen after translations were saved. %1$s: the names of the custom fields, as a list like "price and color". %2$s: the number of translated posts. */
				_n(
					'WPML changed the %1$s values saved on %2$s translation to match its original.',
					'WPML changed the %1$s values saved on %2$s translations to match their originals.',
					$translationCount,
					'sitepress'
				),
				$fieldList,
				number_format_i18n( $translationCount )
			);
		}

		/* translators: Notice on an admin screen. It follows a sentence that says WPML changed values saved on a translation to match the original. "Copy" is the name of a custom field translation setting. */
		$why = _n(
			'This field is set to Copy. It always takes its value from the original.',
			'These fields are set to Copy. They always take their values from the original.',
			$fieldCount,
			'sitepress'
		);

		$remedy = 1 === $translationCount
			/* translators: Notice on an admin screen. It follows a sentence that says a custom field is set to Copy. "Translate" is the name of a custom field translation setting. */
			? _n(
				'To change it, edit the original or set the field to Translate.',
				'To change them, edit the original or set the fields to Translate.',
				$fieldCount,
				'sitepress'
			)
			/* translators: Notice on an admin screen. It follows a sentence that says a custom field of several translated posts is set to Copy. "Translate" is the name of a custom field translation setting. */
			: _n(
				'To change it, edit the originals or set the field to Translate.',
				'To change them, edit the originals or set the fields to Translate.',
				$fieldCount,
				'sitepress'
			);

		return $whatHappened . ' ' . $why . ' ' . $remedy;
	}

	private function getEditOriginalAction( array $resets ) {
		if ( 1 !== count( $resets['translations'] ) ) {
			return null;
		}

		$originalId = (int) current( $resets['translations'] );
		if ( ! current_user_can( 'edit_post', $originalId ) ) {
			return null;
		}

		$editOriginalLink = get_edit_post_link( $originalId, 'raw' );
		if ( ! $editOriginalLink ) {
			return null;
		}

		return [
			/* translators: Link text in the notice that says WPML changed values saved on a translation to match its original. */
			'label' => __( 'Edit the original', 'sitepress' ),
			'url'   => $editOriginalLink,
		];
	}
}
