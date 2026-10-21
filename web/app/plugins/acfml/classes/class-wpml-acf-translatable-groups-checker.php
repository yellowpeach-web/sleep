<?php

use ACFML\FieldGroup\SetCptNotTranslatable;
use ACFML\Notice\Links;

class WPML_ACF_Translatable_Groups_Checker implements \IWPML_Backend_Action, \IWPML_Frontend_Action, \IWPML_DIC_Action {
	const TRANSIENT_KEY = 'acfml_untranslated_groups';
	const POST_TYPE     = 'acf-field-group';
	const NOTICE_GROUP  = 'acfml';
	const NOTICE_ID     = 'acfml-field-group-translation-notice';

	const NOTICE_SCREENS = [ 'edit-acf-field-group', 'acf-field-group' ];

	const NOTICE_PRIORITY = 9;

	const NOTICES_STYLE_HANDLE = 'otgs-notices';

	const STYLE_PRIORITY = 20;

	const PRIMARY_BUTTON_COLOR = '#fff';

	private $untranslated_groups;

	public function add_hooks() {
		if ( is_admin() ) {
			add_action( 'admin_init', [ $this, 'check_untranslated_groups' ] );
			add_action( 'admin_enqueue_scripts', [ $this, 'add_notice_button_style' ], self::STYLE_PRIORITY );
		}
		add_action( 'save_post', [ $this, 'on_save_field_group' ], 10, 2 );

		add_action( 'before_delete_post', [ $this, 'on_delete_field_group' ], 10, 2 );
		add_action( 'wpml_save_cpt_sync_settings', [ $this, 'on_cpt_sync_settings_saved' ] );

		add_action( 'wpml_config_parse_finished', [ $this, 'flush_untranslated_groups' ] );

		add_action( 'acf/update_field_group', [ $this, 'flush_untranslated_groups' ] );
		add_action( 'acf/delete_field_group', [ $this, 'flush_untranslated_groups' ] );
	}

	public function flush_untranslated_groups() {
		delete_transient( self::TRANSIENT_KEY );
	}

	public function add_notice_button_style() {
		if ( ! wp_style_is( self::NOTICES_STYLE_HANDLE, 'registered' ) ) {
			return;
		}

		wp_add_inline_style( self::NOTICES_STYLE_HANDLE, self::get_notice_button_style() );
	}

	public static function get_notice_button_style() {
		$button = '.otgs-notice[data-id="' . self::NOTICE_ID . '"] a.button-primary';

		return $button . ',' . $button . ':hover,' . $button . ':focus{color:' . self::PRIMARY_BUTTON_COLOR . '}';
	}

	public function check_untranslated_groups() {
		if ( ! $this->is_field_groups_translatable() ) {
			$this->remove_untranslated_groups_notice();
			return;
		}

		$this->untranslated_groups = get_transient( self::TRANSIENT_KEY );
		if ( false === $this->untranslated_groups ) {
			$this->untranslated_groups = $this->get_untranslated_field_groups();
			set_transient( self::TRANSIENT_KEY, $this->untranslated_groups );
		}

		$this->untranslated_groups = is_array( $this->untranslated_groups ) ? $this->untranslated_groups : [];
		if ( count( $this->untranslated_groups ) > 0 ) {
			add_action( 'admin_notices', [ $this, 'report_untranslated_groups' ], self::NOTICE_PRIORITY );
		} else {
			$this->remove_untranslated_groups_notice();
		}
	}

	private function remove_untranslated_groups_notice() {
		if ( function_exists( 'wpml_get_admin_notices' ) ) {
			wpml_get_admin_notices()->remove_notice( self::NOTICE_GROUP, self::NOTICE_ID );
		}
	}

	private function get_report_untranslated_groups_message( $is_config_locked ) {
		/* translators: First sentence of the admin notice about the ACF field group post type being translatable. The quoted word is the setting's value in WPML and is translated the same way there. */
		$text  = '<b>' . esc_html__( 'Field groups are set to "Translatable" in WPML settings, which prevents ACF fields from being translated.', 'acfml' ) . '</b> ';
		/* translators: Second sentence of that notice; "This" is the field groups being set to Translatable. */
		$text .= esc_html__( 'This is almost never intended.', 'acfml' );

		if ( $is_config_locked ) {
			/* translators: Line in that notice when the setting comes from a file. Keep the file name in English; "Not translatable" is the setting's value in WPML. */
			$text .= '<br />' . esc_html__( 'A configuration file (wpml-config.xml) controls this setting on this site, so it cannot be changed from here. Edit that file, or ask whoever maintains it to set the ACF field group post type to Not translatable.', 'acfml' );
		}

		/* translators: Last line of that notice; "them" are the copies of the field groups. */
		$text .= '<br />' . esc_html__( 'Field group copies already created in other languages stay on the site. Changing this setting does not delete them.', 'acfml' );

		return $text;
	}

	public function report_untranslated_groups() {
		if ( ! function_exists( 'wpml_get_admin_notices' ) ) {
			return;
		}

		$is_config_locked = SetCptNotTranslatable::isConfigLocked();

		$notices = wpml_get_admin_notices();
		$notice  = $notices->create_notice( self::NOTICE_ID, $this->get_report_untranslated_groups_message( $is_config_locked ), self::NOTICE_GROUP );
		$notice->set_dismissible( true );
		$notice->set_css_class_types( [ 'notice-error' ] );
		$notice->set_restrict_to_screen_ids( self::NOTICE_SCREENS );

		if ( ! $is_config_locked ) {
			$notice->add_action(
				new WPML_Notice_Action(
					/* translators: Button in the admin notice about translatable field groups; it applies the fix. "Not translatable" is the setting's value in WPML and is translated the same way there. Verb phrase, imperative. */
					esc_html__( 'Set field groups to Not translatable', 'acfml' ),
					SetCptNotTranslatable::getUrl(),
					false,
					false,
					'button-primary'
				)
			);
		}

		$why = new WPML_Notice_Action(
			/* translators: Link in that notice; it opens the ACFML documentation. */
			esc_html__( 'Why this matters', 'acfml' ),
			Links::getAcfmlExpertDoc( [ 'anchor' => 'field-group-translation-settings' ] )
		);
		$why->set_link_target( '_blank' );
		$notice->add_action( $why );

		$notices->add_notice( $notice );
	}

	public function on_save_field_group( $post_id, $post ) {
		$this->flush_for_field_group( $post );
	}

	public function on_delete_field_group( $post_id, $post = null ) {
		$this->flush_for_field_group( $post );
	}

	private function flush_for_field_group( $post ) {
		if ( $post instanceof WP_Post && self::POST_TYPE === $post->post_type ) {
			$this->flush_untranslated_groups();
		}
	}

	public function on_cpt_sync_settings_saved() {
		delete_transient( self::TRANSIENT_KEY );
	}

	private function is_field_groups_translatable() {
		return (bool) apply_filters( 'wpml_sub_setting', false, 'custom_posts_sync_option', self::POST_TYPE );
	}

	private function get_untranslated_field_groups() {
		$groups = get_posts(
			[
				'post_type'      => self::POST_TYPE,
				'posts_per_page' => -1,
				'post_status'    => 'any',
			]
		);

		$untranslated = [];
		foreach ( $groups as $group ) {
			if ( ! apply_filters( 'wpml_element_has_translations', null, $group->ID, self::POST_TYPE ) ) {
				$untranslated[] = $group;
			}
		}
		return $untranslated;
	}
}
