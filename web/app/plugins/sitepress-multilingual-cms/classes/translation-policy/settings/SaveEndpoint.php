<?php

namespace WPML\TranslationPolicy\Settings;

use WPML\LIB\WP\User;
use WPML\Request\Adapter\Ajax;
use WPML\Request\Policy\Authenticity;
use WPML\Request\Policy\Policy;

class SaveEndpoint implements \IWPML_Action {

	const ACTION = 'wpml_translation_policy_settings_save';

	const NONCE = 'wpml_translation_policy_settings_save';

	const FIELD_NONCE = 'nonce';

	const FIELD_ITEMS = 'items';

	const ITEM_SCOPE_FIELDS = [ 'scope', 'id' ];

	private $saveSettings;

	private $stateAfterSave;

	public function __construct( callable $saveSettings, callable $stateAfterSave ) {
		$this->saveSettings   = $saveSettings;
		$this->stateAfterSave = $stateAfterSave;
	}

	public function add_hooks() {
		Ajax::register(
			self::ACTION,
			Policy::capability( [ User::CAP_MANAGE_TRANSLATIONS ], Authenticity::actionNonce( self::NONCE, self::FIELD_NONCE ) ),
			[ $this, 'handle' ]
		);
	}

	public function handle() {
		if ( ! check_ajax_referer( self::NONCE, self::FIELD_NONCE, false ) ) {
			wp_send_json_error( [ 'message' => Words::expired() ], 403 );
			return;
		}

		if ( ! User::canManageTranslations() ) {
			wp_send_json_error( [ 'message' => Words::notAllowed() ], 403 );
			return;
		}

		$items = isset( $_POST[ self::FIELD_ITEMS ] )
			? json_decode( sanitize_text_field( wp_unslash( $_POST[ self::FIELD_ITEMS ] ) ), true )
			: null;

		try {
			if ( ! is_array( $items ) ) {
				throw new \InvalidArgumentException( 'The settings items are a JSON list.' );
			}
			$items = self::sanitizeItems( $items );
			call_user_func( $this->saveSettings )->save( $items );
		} catch ( LockedChoice $e ) {
			wp_send_json_error( [ 'message' => wp_specialchars_decode( $e->getMessage(), ENT_QUOTES ) ], 409 );
			return;
		} catch ( \InvalidArgumentException | \RuntimeException $e ) {
			wp_send_json_error( [ 'message' => Words::notSaved() ], 400 );
			return;
		}

		wp_send_json_success( array_merge( call_user_func( $this->stateAfterSave ), [ 'message' => Words::saved() ] ) );
	}

	private static function sanitizeItems( array $items ): array {
		$fields = array_merge( self::ITEM_SCOPE_FIELDS, ScopeValues::COLUMNS );

		return array_map(
			function ( $item ) use ( $fields ) {
				if ( ! is_array( $item ) ) {
					return $item;
				}

				$sanitized = [];
				foreach ( $fields as $field ) {
					if ( ! array_key_exists( $field, $item ) ) {
						continue;
					}
					$value = is_scalar( $item[ $field ] ) ? (string) $item[ $field ] : '';
					if ( sanitize_key( $value ) !== $value ) {
						throw new \InvalidArgumentException( 'A settings field is a key.' );
					}
					$sanitized[ $field ] = $value;
				}

				return $sanitized;
			},
			$items
		);
	}
}
