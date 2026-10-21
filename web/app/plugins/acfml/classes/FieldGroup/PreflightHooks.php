<?php

namespace ACFML\FieldGroup;

use ACFML\Tools\AdminUrl;

class PreflightHooks implements \IWPML_Backend_Action, \IWPML_REST_Action {

	const PREFLIGHT_FILTER = 'wpml_translate_everything_preflight';

	const ADVISORY_ID = 'acfml-unconfigured-field-groups';

	const APPLY_ACTION = 'acfml_apply_recommended_field_group_setup';
	const APPLY_NONCE  = 'acfml_apply_recommended_field_group_setup';

	const RETURN_INPUT        = 'acfml_return';
	const RETURN_SETUP_SCREEN = 'setup-screen';

	const FLUSH_PRIORITY = 20;

	public function add_hooks() {
		add_filter( self::PREFLIGHT_FILTER, [ $this, 'registerProvider' ] );

		add_action( 'acf/update_field_group', [ $this, 'flushInventory' ], self::FLUSH_PRIORITY );
		add_action( 'acf/delete_field_group', [ $this, 'flushInventory' ], self::FLUSH_PRIORITY );
		add_action( 'wpml_custom_fields_sync_option_updated', [ $this, 'flushInventory' ], self::FLUSH_PRIORITY );

		add_action( 'admin_post_' . self::APPLY_ACTION, [ $this, 'applyRecommendedSetup' ] );
	}

	public function registerProvider( $providers ) {
		if ( ! is_array( $providers ) ) {
			$providers = [];
		}

		$providers[] = [ $this, 'getAdvisory' ];

		return $providers;
	}

	public function flushInventory() {
		UnconfiguredGroups::flush();
		SetupInventory::flush();
	}

	public function getAdvisory() {
		$inventory = UnconfiguredGroups::get();

		if ( $inventory['groupCount'] < 1 ) {
			return null;
		}

		return [
			'id'      => self::ADVISORY_ID,
			'message' => self::getMessage( $inventory ),
			'counts'  => [
				/* translators: Label of the count of ACF field groups on the setup notice. Noun, plural. */
				esc_html__( 'Field groups', 'acfml' ) => $inventory['groupCount'],
				/* translators: Label of the count of ACF fields on the setup notice, and the column heading for that count on the translation setup screen. Noun, plural. */
				esc_html__( 'Fields', 'acfml' )       => $inventory['fieldCount'],
				/* translators: Label of the count of posts using these field groups, on the setup notice. Noun, plural. */
				esc_html__( 'Posts', 'acfml' )        => $inventory['postCount'],
			],
			'actions' => [
				[
					'label' => self::getApplyLabel( $inventory['groupCount'] ),
					'url'   => self::getApplyUrl(),
				],
				[
					/* translators: Button on the setup notice that opens the ACF field-groups list; "them" are the field groups. Verb phrase, imperative. */
					'label' => esc_html__( 'Review them one by one', 'acfml' ),
					'url'   => AdminUrl::getFieldGroupsList(),
				],
			],
		];
	}

	public static function getApplyLabel( $groupCount ) {
		return sprintf(
			esc_html(
				/* translators: %d is the number of field groups that have no translation setup. */
				_n(
					'Apply the recommended setup to %d group',
					'Apply the recommended setup to all %d',
					$groupCount,
					'acfml'
				)
			),
			$groupCount
		);
	}

	public static function getMessage( array $inventory ) {
		$lead = sprintf(
			esc_html(
				/* translators: %d is the number of ACF field groups with no translation setup. */
				_n(
					'%d ACF field group is not configured for translation.',
					'%d ACF field groups are not configured for translation.',
					$inventory['groupCount'],
					'acfml'
				)
			),
			$inventory['groupCount']
		);

		$fieldCount = number_format_i18n( $inventory['fieldCount'] );

		$posts = sprintf(
			/* translators: %d is a number of posts. */
			esc_html( _n( '%d post', '%d posts', $inventory['postCount'], 'acfml' ) ),
			$inventory['postCount']
		);

		$defaultLanguage = self::getDefaultLanguageName();

		if ( $defaultLanguage ) {
			$detail = sprintf(
				esc_html(
					/* translators: %1$s is the number of fields in these field groups, %2$s is "41 posts", %3$s the site's default language. */
					_n(
						'%1$s field, used on %2$s, would be skipped and stay in %3$s.',
						'%1$s fields, used on %2$s, would be skipped and stay in %3$s.',
						$inventory['fieldCount'],
						'acfml'
					)
				),
				$fieldCount,
				$posts,
				esc_html( $defaultLanguage )
			);
		} else {
			$detail = sprintf(
				esc_html(
					/* translators: %1$s is the number of fields in these field groups, %2$s is "41 posts". */
					_n(
						'%1$s field, used on %2$s, would be skipped and stay untranslated.',
						'%1$s fields, used on %2$s, would be skipped and stay untranslated.',
						$inventory['fieldCount'],
						'acfml'
					)
				),
				$fieldCount,
				$posts
			);
		}

		return $lead . ' ' . $detail;
	}

	private static function getDefaultLanguageName() {
		$code = apply_filters( 'wpml_default_language', null );
		if ( ! is_string( $code ) || '' === $code ) {
			return null;
		}

		$name = apply_filters( 'wpml_translated_language_name', null, $code, null );

		return is_string( $name ) && '' !== $name ? $name : null;
	}

	public static function getApplyUrl() {
		return esc_url_raw(
			add_query_arg(
				[
					'action'          => self::APPLY_ACTION,
					self::APPLY_NONCE => wp_create_nonce( self::APPLY_NONCE ),
				],
				admin_url( 'admin-post.php' )
			)
		);
	}

	public function applyRecommendedSetup() {
		if ( ! self::canEditFieldGroups() ) {
			$this->refuse();

			return;
		}

		check_admin_referer( self::APPLY_NONCE, self::APPLY_NONCE );

		$applied = (int) $this->getBulkEngine()->run();
		$this->flushInventory();

		$this->redirect( $this->getReturnUrl( $applied ) );
	}

	private function getReturnUrl( $applied = 0 ) {
		$requested = isset( $_REQUEST[ self::RETURN_INPUT ] ) ? sanitize_key( wp_unslash( $_REQUEST[ self::RETURN_INPUT ] ) ) : '';

		if ( self::RETURN_SETUP_SCREEN === $requested ) {
			return SetupScreen::getUrl( [ SetupScreen::APPLIED_INPUT => (string) $applied ] );
		}

		return AdminUrl::getFieldGroupsList();
	}

	private static function canEditFieldGroups() {
		if ( function_exists( 'acf_current_user_can_admin' ) ) {
			return (bool) acf_current_user_can_admin();
		}

		return current_user_can( 'manage_options' );
	}

	protected function getBulkEngine() {
		return new SetSameFieldsModeAsDefault();
	}

	protected function refuse() {
		wp_die(
			/* translators: Error page shown when the logged-in user may not change the field groups' translation setup. */
			esc_html__( 'You are not allowed to change the translation setup of ACF field groups.', 'acfml' ),
			'',
			[ 'response' => 403 ]
		);
	}

	protected function redirect( $url ) {
		wp_safe_redirect( $url );
		exit;
	}
}
