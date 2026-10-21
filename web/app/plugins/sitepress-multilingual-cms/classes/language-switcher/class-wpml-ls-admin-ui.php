<?php

use WPML\API\Sanitize;
use WPML\Core\Component\PostHog\Application\Service\Event\EventInstanceService;

class WPML_LS_Admin_UI extends WPML_Templates_Factory {

	const NONCE_NAME            = 'wpml-language-switcher-admin';
	const MAIN_UI_TEMPLATE      = 'layout-main.twig';
	const RESET_UI_TEMPLATE     = 'layout-reset.twig';
	const BUTTON_TEMPLATE       = 'layout-slot-edit-button.twig';
	const SLOT_SLUG_PLACEHOLDER = '%id%';
	const RESET_NONCE_NAME      = 'wpml-language-switcher-reset';

	const SETTINGS_FORM_KEYS = array(
		'languages_order',
		'link_empty',
		'copy_parameters',
		'additional_css',
		'menus',
		'sidebars',
		'statics',
		'submit_setup_wizard',
	);

	const SLOT_FIELDS_NOT_FROM_REQUEST = array(
		'template_string',
	);

	private $templates;

	private $settings;

	private $render;

	private $inline_styles;

	private $assets;

	private $sitepress;

	public function __construct( $templates, $settings, $render, $inline_styles, $sitepress, $assets = null ) {
		$this->templates     = $templates;
		$this->settings      = $settings;
		$this->render        = $render;
		$this->inline_styles = $inline_styles;
		$this->assets        = $assets ? $assets : new WPML_LS_Assets( $this->templates, $this->settings );
		$this->sitepress     = $sitepress;
		parent::__construct();
	}

	public function init_hooks() {
		add_filter( 'wpml_admin_languages_navigation_items', array( $this, 'languages_navigation_items_filter' ) );
		add_action( 'wpml_admin_after_languages_url_format', array( $this, 'after_languages_url_format_action' ) );
		add_action( 'wpml_admin_after_wpml_love', array( $this, 'after_wpml_love_action' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts_action' ) );
		add_action( 'admin_head', array( $this, 'admin_head_action' ) );
		\WPML\Request\Adapter\Ajax::register( 'wpml-ls-save-settings', \WPML\Request\Policy\Policy::capability( 'wpml_manage_languages', \WPML\Request\Policy\Authenticity::actionNonce( 'wpml-language-switcher-admin', 'nonce' ) ), array( $this, 'save_settings_action' ) );
		\WPML\Request\Adapter\Ajax::register( 'wpml-ls-update-preview', \WPML\Request\Policy\Policy::capability( 'wpml_manage_languages', \WPML\Request\Policy\Authenticity::actionNonce( 'wpml-language-switcher-admin', 'nonce' ) ), array( $this, 'update_preview_action' ) );
	}

	public static function get_page_hook() {
		return WPML_PLUGIN_FOLDER . '/menu/languages.php';
	}

	public function admin_enqueue_scripts_action( $hook ) {
		if ( self::get_page_hook() === $hook ) {
			$suffix = $this->sitepress->get_wp_api()->constant( 'SCRIPT_DEBUG' ) ? '' : '.min';

			wp_register_script(
				'wpml-language-switcher-settings',
				ICL_PLUGIN_URL . '/res/js/language-switchers-settings' . $suffix . '.js',
				array( 'jquery', 'wp-util', 'jquery-ui-sortable', 'jquery-ui-dialog', 'wp-color-picker', 'wp-pointer', 'wpml-tooltip' ),
				ICL_SITEPRESS_SCRIPT_VERSION
			);
			wp_enqueue_script( 'wpml-language-switcher-settings' );

			wp_enqueue_style( 'wp-pointer' );
			wp_enqueue_style( 'wp-color-picker' );

			wp_register_style(
				'wpml-language-switcher-settings',
				ICL_PLUGIN_URL . '/res/css/wpml-language-switcher-settings.css',
				array(),
				ICL_SITEPRESS_SCRIPT_VERSION
			);
			wp_enqueue_style( 'wpml-language-switcher-settings' );

			$js_vars = array(
				'nonce'         => wp_create_nonce( self::NONCE_NAME ),
				'menus'         => $this->settings->get_available_menus(),
				'sidebars'      => $this->settings->get_registered_sidebars(),
				'color_schemes' => $this->settings->get_default_color_schemes(),
				'strings'       => $this->get_javascript_strings(),
				'templates'     => $this->templates->get_all_templates_data(),
			);

			wp_localize_script( 'wpml-language-switcher-settings', 'wpml_language_switcher_admin', $js_vars );

			$this->assets->wp_enqueue_scripts_action();
		}
	}

	public function admin_head_action() {
		$this->inline_styles->admin_output();
	}

	public function save_settings_action() {
		if ( \WPML\Setup\Initializer::rejectSettingsMutationAjax() ) {
			return;
		}

		if ( $this->has_valid_nonce() && isset( $_POST['settings'] ) ) {
			$new_settings = $this->parse_request_settings( 'settings' );

			if ( ! $new_settings ) {
				$this->sitepress->get_wp_api()->wp_send_json_error(
					/* translators: Error shown on the language switcher settings screen when the save request arrived without the settings. */
					esc_html__( 'The settings were not saved because they did not reach the server. Reload the page and save again.', 'sitepress' )
				);
				return;
			}

			$old_settings = $this->settings->get_settings();

			$this->settings->save_settings( $new_settings );

			$this->capturePostHogEventForFooterLSChange( $old_settings, $new_settings );

			$this->maybe_complete_setup_wizard_step( $new_settings );
			/* translators: Notice shown after the language switcher settings are saved. */
			$this->sitepress->get_wp_api()->wp_send_json_success( esc_html__( 'Settings saved', 'sitepress' ) );
		} else {
			$this->sitepress->get_wp_api()->wp_send_json_error( esc_html__( "You can't do that!", 'sitepress' ) );
		}
	}

	private function capturePostHogEventForFooterLSChange( $oldSettings, $newSettings ) {
		$oldFooterLSEnabled = isset( $oldSettings['statics']['footer'] )
								&& $oldSettings['statics']['footer']->get( 'show' );

		$newFooterLSEnabled = isset( $newSettings['statics']['footer']['show'] )
								&& ! empty( $newSettings['statics']['footer']['show'] );

		if ( $oldFooterLSEnabled !== $newFooterLSEnabled ) {
			$eventProps = array(
				'enabled' => $newFooterLSEnabled,
				'source'  => 'languages_page',
			);

			\WPML\PostHog\Event\CaptureEvent::capture(
				( new EventInstanceService() )->getFooterLanguageSwitcherToggledEvent( $eventProps )
			);
		}
	}

	private function maybe_complete_setup_wizard_step( $new_settings ) {
		if ( isset( $new_settings['submit_setup_wizard'] ) && $new_settings['submit_setup_wizard'] == 1 ) {
			$setup_instance = wpml_get_setup_instance();
			$setup_instance->finish_step3();
		}
	}

	public function update_preview_action() {
		$preview = false;

		if ( $this->has_valid_nonce() ) {
			$settings = $this->parse_request_settings( 'slot_settings' );

			foreach ( array( 'menus', 'sidebars', 'statics' ) as $group ) {
				if ( ! empty( $settings[ $group ] ) ) {
					$slot_slug = key( $settings[ $group ] );

					if ( preg_match( '/' . self::SLOT_SLUG_PLACEHOLDER . '/', $slot_slug ) ) {
						$new_slug                        = preg_replace( '/' . self::SLOT_SLUG_PLACEHOLDER . '/', '__id__', $slot_slug );
						$settings[ $group ][ $new_slug ] = $settings[ $group ][ $slot_slug ];
						unset( $settings[ $group ][ $slot_slug ] );
						$slot_slug = $new_slug;
					}

					$settings[ $group ][ $slot_slug ]['slot_slug']  = $slot_slug;
					$settings[ $group ][ $slot_slug ]['slot_group'] = $group;
					$settings                                       = $this->settings->convert_slot_settings_to_objects( $settings );
					$slot    = $settings[ $group ][ $slot_slug ];
					$preview = $this->render->get_preview( $slot );
					$this->sitepress->get_wp_api()->wp_send_json_success( $preview );
				}
			}
		}

		if ( ! $preview ) {
			$this->sitepress->get_wp_api()->wp_send_json_error( esc_html__( 'Preview update failed', 'sitepress' ) );
		}
	}

	private function parse_request_settings( $key ) {
		$settings = array_key_exists( $key, $_POST ) ? $_POST[ $key ] : null;
		$settings = is_string( $settings ) ? wp_unslash( $settings ) : $settings;

		return self::read_settings_form( $settings );
	}

	public static function read_settings_form( $form ) {
		if ( ! is_string( $form ) || '' === $form ) {
			return array();
		}

		parse_str( self::undo_html_escaping( $form ), $fields );

		if ( ! array_intersect_key( $fields, array_flip( self::SETTINGS_FORM_KEYS ) ) ) {
			return array();
		}

		$settings = array();
		foreach ( $fields as $name => $value ) {
			switch ( $name ) {
				case 'additional_css':
					$settings[ $name ] = is_scalar( $value ) ? wp_strip_all_tags( (string) $value ) : '';
					break;
				case 'languages_order':
					$settings[ $name ] = is_array( $value )
						? array_values(
							array_map(
								function ( $code ) {
									return sanitize_text_field( (string) $code );
								},
								array_filter( $value, 'is_scalar' )
							)
						)
						: array();
					break;
				case 'menus':
				case 'sidebars':
				case 'statics':
					$settings[ $name ] = self::read_slot_group( $value );
					break;
				default:
					if ( is_scalar( $value ) ) {
						$settings[ $name ] = sanitize_text_field( (string) $value );
					}
			}
		}

		return $settings;
	}

	private static function undo_html_escaping( $form ) {
		return (string) preg_replace(
			array( '/&(?:amp|#0*38|#x0*26);/i', '/&(?:apos|#0*39|#x0*27);/i' ),
			array( '&', "'" ),
			$form
		);
	}

	private static function read_slot_group( $slots ) {
		$group = array();

		if ( ! is_array( $slots ) ) {
			return $group;
		}

		foreach ( $slots as $slug => $fields ) {
			if ( ! is_array( $fields ) ) {
				continue;
			}

			$slot = array();
			foreach ( $fields as $property => $value ) {
				if ( in_array( $property, self::SLOT_FIELDS_NOT_FROM_REQUEST, true ) ) {
					continue;
				}
				if ( is_scalar( $value ) ) {
					$slot[ $property ] = (string) $value;
				}
			}
			$group[ $slug ] = $slot;
		}

		return $group;
	}

	private function has_valid_nonce() {
		$nonce = Sanitize::stringProp( 'nonce', $_POST );

		return $nonce
			? (bool) wp_verify_nonce( $nonce, self::NONCE_NAME )
			: false;
	}

	public function languages_navigation_items_filter( $items ) {
		$item_to_insert  = array( '#wpml-ls-settings-form' => esc_html__( 'Language switcher options', 'sitepress' ) );
		$insert_position = array_search( '#lang-sec-2', array_keys( $items ), true ) + 1;

		$items = array_merge( array_slice( $items, 0, $insert_position ), $item_to_insert, array_slice( $items, $insert_position ) );

		/* translators: Heading of the panel that resets the language switcher settings. */
		$items['#wpml_ls_reset'] = esc_html__( 'Reset settings', 'sitepress' );

		return $items;
	}

	public function after_languages_url_format_action() {
		$setup_wizard_step = (int) $this->sitepress->get_setting( 'setup_wizard_step' );
		$setup_complete    = $this->sitepress->get_setting( 'setup_complete' );
		$active_languages  = $this->sitepress->get_active_languages();

		if ( 3 === $setup_wizard_step || ( ! empty( $setup_complete ) && count( $active_languages ) > 1 ) ) {
			$this->show( self::MAIN_UI_TEMPLATE, $this->get_main_ui_model() );
		}
	}

	public function after_wpml_love_action( $theme_wpml_config_file ) {
		$setup_complete   = $this->sitepress->get_setting( 'setup_complete' );
		$active_languages = $this->sitepress->get_active_languages();

		if ( $setup_complete && count( $active_languages ) > 1 ) {
			$this->show( self::RESET_UI_TEMPLATE, $this->get_reset_ui_model( $theme_wpml_config_file ) );
		}
	}

	public function get_button_to_edit_slot( $type, $slug_or_id ) {
		$slug = $slug_or_id;

		if ( 'menus' === $type ) {
			$menu = wp_get_nav_menu_object( $slug_or_id );
			$slug = isset( $menu->term_id ) ? $menu->term_id : null;
		}

		$slot = $this->settings->get_slot( $type, $slug );
		$url  = admin_url( 'admin.php?page=' . self::get_page_hook() . '#' . $type . '/' . $slug );

		if ( $slot->slug() === $slug ) {
			$model = array(
				'action' => 'edit',
				'url'    => $url,
				'label'  => __( 'Customize the language switcher', 'sitepress' ),
			);
		} else {
			$model = array(
				'action' => 'add',
				'url'    => $url,
				'label'  => __( 'Add a language switcher', 'sitepress' ),
			);
		}

		return $this->get_view( self::BUTTON_TEMPLATE, $model );
	}

	protected function init_template_base_dir() {
		$this->template_paths = array(
			WPML_PLUGIN_PATH . '/templates/language-switcher-admin-ui/',
		);
	}

	public function get_template() {
		return self::MAIN_UI_TEMPLATE;
	}

	private function get_all_previews() {
		$previews = array();

		foreach ( array( 'menus', 'sidebars', 'statics' ) as $slot_group ) {

			$previews[ $slot_group ] = array();

			foreach ( $this->settings->get_setting( $slot_group ) as $slug => $slot_settings ) {
				$prev = $this->render->get_preview( $slot_settings );

				foreach ( array( 'html', 'css', 'js', 'styles' ) as $preview_part ) {
					$previews[ $slot_group ][ $slug ][ $preview_part ] = $prev[ $preview_part ];
				}
			}
		}

		return $previews;
	}

	public function get_model() {
		return array();
	}

	public function get_main_ui_model() {
		$slot_factory = new WPML_LS_Slot_Factory();

		$model = array(
			'strings'                  => array(
				'misc'              => $this->get_misc_strings(),
				'tooltips'          => $this->get_tooltip_strings(),
				'color_picker'      => $this->get_color_picker_strings(),
				'options'           => $this->get_options_section_strings(),
				'menus'             => $this->get_menus_section_strings(),
				'sidebars'          => $this->get_sidebars_section_strings(),
				'footer'            => $this->get_footer_section_strings(),
				'post_translations' => $this->get_post_translations_strings(),
				'shortcode_actions' => $this->get_shortcode_actions_strings(),
			),
			'data'                     => array(
				'templates' => $this->templates->get_templates(),
				'menus'     => $this->settings->get_available_menus(),
				'sidebars'  => $this->settings->get_registered_sidebars(),
			),
			'ordered_languages'        => $this->settings->get_ordered_languages(),
			'settings'                 => $this->settings->get_settings_model(),
			'settings_slug'            => $this->settings->get_settings_base_slug(),
			'previews'                 => $this->get_all_previews(),
			'color_schemes'            => $this->settings->get_default_color_schemes(),
			'notifications'            => $this->get_notifications(),
			'default_menus_slot'       => $slot_factory->get_default_slot_arguments( 'menus' ),
			'default_sidebars_slot'    => $slot_factory->get_default_slot_arguments( 'sidebars' ),
			'setup_complete'           => $this->sitepress->get_setting( 'setup_complete' ),
			'setup_step_2_nonce_field' => wp_nonce_field( 'setup_got_to_step2_nonce', '_icl_nonce_gts2', true, false ),
		);

		return $model;
	}

	public function get_misc_strings() {
		return array(
			'no_templates'                          => __( 'There are no templates available.', 'sitepress' ),
			'label_preview'                         => _x( 'Preview', 'Language switcher preview', 'sitepress' ),
			'label_position'                        => _x( 'Position', 'Language switcher preview', 'sitepress' ),
			'label_actions'                         => _x( 'Actions', 'Language switcher preview', 'sitepress' ),
			'label_action'                          => _x( 'Action', 'Language switcher preview', 'sitepress' ),
			/* translators: Button label that keeps what was entered. Verb, imperative. */
			'button_save'                           => __( 'Save', 'sitepress' ),
			/* translators: Button label that closes a dialog without doing anything, or stops what is going on. Verb, imperative, not the noun "a cancellation". */
			'button_cancel'                         => __( 'Cancel', 'sitepress' ),
			'title_what_to_include'                 => __( 'What to include in the language switcher:', 'sitepress' ),
			/* translators: Label of the checkbox that includes the flag image in the language switcher, and column heading in the table of languages. Noun: the flag picture of a country or language, never the verb "to flag". */
			'label_include_flag'                    => __( 'Flag', 'sitepress' ),
			'label_include_native_lang'             => __( 'Native language name', 'sitepress' ),
			'label_include_display_lang'            => __( 'Language name in current language', 'sitepress' ),
			/* translators: Label of the checkbox that includes the current language in the language switcher. */
			'label_include_current_lang'            => __( 'Current language', 'sitepress' ),
			/* translators: Label of the field that sets the width of the flag image, in pixels. */
			'label_include_flag_width'              => __( 'Width', 'sitepress' ),
			/* translators: Label of the field that sets the height of the flag image, in pixels. */
			'label_include_flag_height'             => __( 'Height', 'sitepress' ),
			/* translators: Placeholder text in the flag width and height fields: the size is chosen automatically. */
			'label_include_flag_width_placeholder'  => __( 'auto', 'sitepress' ),
			/* translators: Placeholder text in the flag width and height fields: the size is chosen automatically. */
			'label_include_flag_height_placeholder' => __( 'auto', 'sitepress' ),
			/* translators: Unit shown after the flag width and height fields: pixels. Keep the abbreviation used for pixels in your language. */
			'label_include_flag_width_suffix'       => __( 'px', 'sitepress' ),
			/* translators: Unit shown after the flag width and height fields: pixels. Keep the abbreviation used for pixels in your language. */
			'label_include_flag_height_suffix'      => __( 'px', 'sitepress' ),
			'templates_dropdown_label'              => __( 'Language switcher style:', 'sitepress' ),
			/* translators: The name of the plugin, used as the title of its screens and of its menu. It is a product name and stays as it is. */
			'templates_wpml_group'                  => __( 'WPML', 'sitepress' ),
			/* translators: Adjective, in two language switcher dropdowns: the group heading for styles that come from the theme rather than from WPML, and the Elementor option for the switcher built from the settings below. */
			'templates_custom_group'                => __( 'Custom', 'sitepress' ),
			'title_action_edit'                     => __( 'Edit language switcher', 'sitepress' ),
			'title_action_delete'                   => __( 'Delete language switcher', 'sitepress' ),
			/* translators: Button label in the language switcher dialog: go back to the previous step. */
			'button_back'                           => __( 'Back', 'sitepress' ),
			/* translators: Button label in the language switcher dialog: go on to the next step. Verb phrase for moving forward, not the adverb "next". */
			'button_next'                           => __( 'Next', 'sitepress' ),
		);
	}

	public function get_tooltip_strings() {
		return array(
			'languages_order'               => array(
				/* translators: Tooltip next to the list of languages on the language switcher settings screen. */
				'text' => __( 'This is the order in which the languages will be displayed in the language switcher.', 'sitepress' ),
			),
			'languages_without_translation' => array(
				'text' => __( 'Some content may not be translated to all languages. Choose what should appear in the language switcher when translation is missing.', 'sitepress' ),
			),
			'preserve_url_arguments'        => array(
				'text' => __( 'Add a comma-separated list of URL arguments that you want WPML to pass when switching languages.', 'sitepress' ),
				'link' => array(
					'text'   => __( 'Preserving URL arguments', 'sitepress' ),
					'url'    => \WPML\OutboundLinks\OutboundLinks::to(
						'https://wpml.org/documentation/getting-started-guide/language-switcher-options/preserve-url-arguments-when-switching-languages/',
						array(
							'medium'   => 'settings',
							'campaign' => 'language-switcher',
						)
					),
					'target' => '_blank',
				),
				'id'   => 'preserve_url_arguments_tooltip',
			),
			'additional_css'                => array(
				/* translators: Tooltip next to the additional CSS field on the language switcher settings screen. */
				'text' => __( 'Enter CSS to add to the page. This is useful when you want to add styling to the language switcher, without having to edit the CSS file on the server.', 'sitepress' ),
				'link' => array(
					'text'   => __( 'Styling the language switcher with additional CSS', 'sitepress' ),
					'url'    => \WPML\OutboundLinks\OutboundLinks::to(
						'https://wpml.org/documentation/getting-started-guide/language-switcher-options/how-to-fix-styling-and-css-issues-for-the-language-switchers/',
						array(
							'medium'   => 'settings',
							'campaign' => 'language-switcher',
						)
					),
					'target' => '_blank',
				),
				'id'   => 'additional_css_tooltip',
			),
			'section_post_translations'     => array(
				'text' => __( 'You can display links to translation of posts before the post and after it. These links look like "This post is also available in..."', 'sitepress' ),
			),
			'add_menu_all_assigned'         => array(
				'text' => __( 'The button is disabled because all existing menus have language switchers. You can edit the settings of the existing language switchers.', 'sitepress' ),
			),
			'add_menu_no_menu'              => array(
				'text' => __( 'The button is disabled because there are no menus in the site. Add a menu and you can later enable a language switcher in it.', 'sitepress' ),
			),
			'add_sidebar_all_assigned'      => array(
				'text' => __( 'The button is disabled because all existing widget areas have language switchers. You can edit the settings of the existing language switchers.', 'sitepress' ),
			),
			'add_sidebar_no_sidebar'        => array(
				'text' => __( 'The button is disabled because there are no registered widget areas in the site.', 'sitepress' ),
			),
			'what_to_include'               => array(
				/* translators: Tooltip next to the list of items (flag, language name, current language) that can be shown in the language switcher. */
				'text' => __( 'Elements to include in the language switcher.', 'sitepress' ),
			),
			'available_menus'               => array(
				'text' => __( 'Select the menus, in which to display the language switcher.', 'sitepress' ),
				'id'   => 'available_menus_tooltip',
			),
			'available_sidebars'            => array(
				'text' => __( 'Select the widget area where to include the language switcher.', 'sitepress' ),
			),
			'available_templates'           => array(
				'text' => __( 'Select the style of the language switcher.', 'sitepress' ),
			),
			'menu_style_type'               => array(
				'text' => __( 'Select how to display the language switcher in the menu. Choose "List of languages" to display all the items at the same level or "Dropdown" to display the current language as parent and other languages as children.', 'sitepress' ),
			),
			'menu_position'                 => array(
				'text' => __( 'Select the position to display the language switcher in the menu.', 'sitepress' ),
			),
			'widget_title'                  => array(
				'text' => __( 'Enter the title of the widget or leave empty for no title.', 'sitepress' ),
			),
			'post_translation_position'     => array(
				'text' => __( 'Select the position to display the post translations links.', 'sitepress' ),
			),
			'alternative_languages_text'    => array(
				/* translators: Tooltip next to the field where the user types the text shown above the links to the other languages. %s is shown to the reader as it is: it is the marker the user has to type in that text, and the language links replace it. Keep it unchanged. */
				'text' => __( 'This text appears before the list of languages. Your text needs to include the string %s which is a placeholder for the actual links.', 'sitepress' ),
			),
			'backwards_compatibility'       => array(
				/* translators: Tooltip next to the backwards compatibility option on the language switcher settings screen. */
				'text' => __( "Since WPML 3.6.0, the language switchers are not using CSS IDs and the CSS classes have changed. This was required to fix some bugs and match the latest standards. If your theme or your custom CSS is not relying on these old selectors, it's recommended to skip the backwards compatibility. However, it's still possible to re-activate this option later.", 'sitepress' ),
				'id'   => 'backwards_compatibility_tooltip',
			),
			'show_in_footer'                => array(
				'text' => __( "You can display a language switcher in the site's footer. You can customize and style it here.", 'sitepress' ),
			),
		);
	}

	public function get_options_section_strings() {
		return array(
			'section_title'                        => __( 'Language switcher options', 'sitepress' ),
			'label_language_order'                 => __( 'Order of languages', 'sitepress' ),
			'tip_drag_languages'                   => __( 'Drag and drop the languages to change their order', 'sitepress' ),
			'label_languages_with_no_translations' => __( 'How to handle languages without translation', 'sitepress' ),
			/* translators: Option in the dropdown that says what the language switcher does for a language that has no translation: leave that language out. */
			'option_skip_link'                     => __( 'Skip language', 'sitepress' ),
			'option_link_home'                     => __( 'Link to home of language for missing translations', 'sitepress' ),
			'label_preserve_url_args'              => __( 'Preserve URL arguments', 'sitepress' ),
			/* translators: Label of the field where extra CSS for the language switcher is entered. */
			'label_additional_css'                 => __( 'Additional CSS', 'sitepress' ),
			/* translators: Label of the option that keeps the CSS classes and IDs used by language switchers before WPML 3.6.0. */
			'label_migrated_toggle'                => __( 'Backwards compatibility', 'sitepress' ),
			'label_skip_backwards_compatibility'   => __( 'Skip backwards compatibility', 'sitepress' ),
		);
	}

	public function get_menus_section_strings() {
		return array(
			'section_title'         => __( 'Menu language switcher', 'sitepress' ),
			'add_button_label'      => __( 'Add a new language switcher to a menu', 'sitepress' ),
			/* translators: Label of the dropdown that picks the site menu the language switcher is added to. */
			'select_label'          => __( 'Menu', 'sitepress' ),
			'select_option_choose'  => __( 'Choose a menu', 'sitepress' ),
			/* translators: Label of the dropdown that picks where the language switcher sits inside the menu. */
			'position_label'        => __( 'Position:', 'sitepress' ),
			'position_first_item'   => __( 'First menu item', 'sitepress' ),
			'position_last_item'    => __( 'Last menu item', 'sitepress' ),
			'is_hierarchical_label' => __( 'Language menu items style:', 'sitepress' ),
			'flat'                  => __( 'List of languages', 'sitepress' ),
			/* translators: Second line under the "List of languages" menu style, shown as an explanation of that style. Starts in lower case because it follows the style name. */
			'flat_desc'             => __( 'good for menus that display items as a list', 'sitepress' ),
			/* translators: Name of a menu style on the language switcher settings screen: the languages open from the current language as a drop-down. */
			'hierarchical'          => __( 'Dropdown', 'sitepress' ),
			/* translators: Second line under the "Dropdown" menu style, shown as an explanation of that style. Starts in lower case because it follows the style name. */
			'hierarchical_desc'     => __( 'good for menus that support drop-downs', 'sitepress' ),
			'dialog_title'          => __( 'Edit Menu Language Switcher', 'sitepress' ),
			'dialog_title_new'      => __( 'New Menu Language Switcher', 'sitepress' ),
		);
	}

	public function get_sidebars_section_strings() {
		return array(
			'section_title'        => __( 'Widget language switcher', 'sitepress' ),
			'add_button_label'     => __( 'Add a new language switcher to a widget area', 'sitepress' ),
			/* translators: Label of the dropdown that picks the widget area the language switcher is added to. */
			'select_label'         => __( 'Widget area', 'sitepress' ),
			'select_option_choose' => __( 'Choose a widget area', 'sitepress' ),
			/* translators: Label of the field that sets the title shown above the language switcher widget. */
			'label_widget_title'   => __( 'Widget title:', 'sitepress' ),
			'dialog_title'         => __( 'Edit Widget Area Language Switcher', 'sitepress' ),
			'dialog_title_new'     => __( 'New Widget Area Language Switcher', 'sitepress' ),
		);
	}

	public function get_footer_section_strings() {
		return array(
			'section_title' => __( 'Footer language switcher', 'sitepress' ),
			'show'          => __( 'Show language switcher in footer', 'sitepress' ),
			'dialog_title'  => __( 'Edit Footer Language Switcher', 'sitepress' ),
		);
	}

	public function get_post_translations_strings() {
		return array(
			'section_title'                      => __( 'Links to translation of posts', 'sitepress' ),
			'show'                               => __( 'Show links above or below posts, offering them in other languages', 'sitepress' ),
			'position_label'                     => __( 'Position of link(s):', 'sitepress' ),
			/* translators: Option that puts the links to the other languages above the post text. */
			'position_above'                     => __( 'Above post', 'sitepress' ),
			/* translators: Option that puts the links to the other languages below the post text. */
			'position_below'                     => __( 'Below post', 'sitepress' ),
			'label_alternative_languages_text'   => __( 'Text for alternative languages for posts:', 'sitepress' ),
			/* translators: Default text shown above the links to the other translations of a post; the site owner can edit it. %s: the list of links to the other languages. */
			'default_alternative_languages_text' => __( 'This post is also available in: %s', 'sitepress' ),
			'dialog_title'                       => __( 'Edit Post Translations Language Switcher', 'sitepress' ),
		);
	}

	public function get_shortcode_actions_strings() {

		/* translators: Link text inside the sentence "Need more options? See how you can ..." on the language switcher settings screen. Starts in lower case because it continues that sentence. */
		$description_link_text = _x( "insert WPML's switchers in custom locations", 'Custom languuage switcher description: external link text', 'sitepress' );
		$description_link_url  = \WPML\OutboundLinks\OutboundLinks::to(
			'https://wpml.org/documentation/getting-started-guide/language-switcher-options/adding-language-switchers-using-php-and-shortcodes/',
			array(
				'medium'   => 'settings',
				'campaign' => 'language-switcher',
			)
		);
		$description_link      = '<a href="' . $description_link_url . '" target="_blank">' . $description_link_text . '</a>';
		/* translators: Sentence on the language switcher settings screen. %s: a link, already wrapped in its tags, whose text is "insert WPML's switchers in custom locations". */
		$description           = _x( 'Need more options? See how you can %s.', 'Custom languuage switcher description: text', 'sitepress' );

		return array(
			'section_title'          => __( 'Custom language switchers', 'sitepress' ),
			'section_description'    => sprintf( $description, $description_link ),
			/* translators: Label of the checkbox that turns a language switcher on. */
			'show'                   => __( 'Enable', 'sitepress' ),
			/* translators: Button label that opens the settings of the language switcher so its look can be changed. */
			'customize_button_label' => __( 'Customize', 'sitepress' ),
			'dialog_title'           => __( 'Edit Shortcode Actions Language Switcher', 'sitepress' ),
		);
	}

	public function get_color_picker_strings() {
		return array(
			'panel_title'          => __( 'Language switcher colors', 'sitepress' ),
			/* translators: Label of the dropdown that picks a ready-made set of colors for the language switcher. */
			'label_color_preset'   => __( 'Color themes:', 'sitepress' ),
			'select_option_choose' => __( 'Select a preset', 'sitepress' ),
			/* translators: Heading of the color settings that apply to the language switcher in its normal state, as opposed to when the mouse is over it. */
			'label_normal_scheme'  => __( 'Normal', 'sitepress' ),
			/* translators: Heading of the color settings that apply while the mouse is over the language switcher. */
			'label_hover_scheme'   => __( 'Hover', 'sitepress' ),
			/* translators: Label of the color field that sets the background color of the language switcher. */
			'background'           => __( 'Background', 'sitepress' ),
			/* translators: Label of the color field that sets the border color of the language switcher. */
			'border'               => __( 'Border', 'sitepress' ),
			'font_current'         => __( 'Current language font color', 'sitepress' ),
			'background_current'   => __( 'Current language background color', 'sitepress' ),
			'font_other'           => __( 'Other language font color', 'sitepress' ),
			'background_other'     => __( 'Other language background color', 'sitepress' ),
		);
	}

	public function get_javascript_strings() {
		return array(
			'confirmation_item_remove' => esc_html__( 'Do you really want to remove this item?', 'sitepress' ),
			'leave_text_box_to_save'   => esc_html__( 'Leave the text box to auto-save', 'sitepress' ),
			'menu_option_not_chosen'   => __( 'Choose which menu to display your language switcher', 'sitepress' ),
			'widget_option_not_chosen' => __( 'Choose which widget to display your language switcher', 'sitepress' ),
		);
	}

	public function get_reset_ui_model( $theme_wpml_config_file ) {
		/* translators: List of the places where language switchers are reset, shown inside the sentence "This will change the settings of your language switchers (in options, menus, widgets, footer and shortcode) to their defaults ...". */
		$reset_locations = esc_html__( 'in options, menus, widgets, footer and shortcode', 'sitepress' );

		$model = array(
			/* translators: Heading of the panel that resets the language switcher settings. */
			'title'                => __( 'Reset settings', 'sitepress' ),
			/* translators: Text of the panel that resets the language switcher settings. %s: the list of the places that are reset, in bold and in brackets, for example "(in options, menus, widgets, footer and shortcode)". */
			'description'          => sprintf( esc_html__( 'This will change the settings of your language switchers %s to their defaults as set by the theme. Please note that some switchers may be removed and others may be added.', 'sitepress' ), '<strong>(' . $reset_locations . ')</strong>' ),
			'theme_config_file'    => $theme_wpml_config_file,
			/* translators: Note under the reset panel, shown when the theme carries its own WPML settings file. %s: the name of that file, in bold. */
			'explanation_text'     => sprintf( esc_html__( '* Your theme has a %s file, which sets the default values for WPML.', 'sitepress' ), '<strong title="' . esc_attr( (string) $theme_wpml_config_file ) . '">wpml-config.xml</strong>' ),
			'confirmation_message' => __( 'Are you sure you want to reset to the default settings?', 'sitepress' ),
			'restore_page_url'     => admin_url( 'admin.php?page=' . self::get_page_hook() . '&restore_ls_settings=1&nonce=' . wp_create_nonce( self::RESET_NONCE_NAME ) ),
			/* translators: Button label that resets the language switcher settings to the values set by the theme. */
			'restore_button_label' => __( 'Restore default', 'sitepress' ),
		);

		return $model;
	}

	private function get_notifications() {
		$notifications = array();

		if ( $this->sitepress->get_wp_api()->constant( 'ICL_DONT_LOAD_LANGUAGE_SELECTOR_CSS' ) ) {
			$notifications['css_not_loaded'] = sprintf(
				/* translators: Notice on the language switcher settings screen. %s: the name of a setting defined in the theme, shown as it is written in the code. */
				__( "%s is defined in your theme. The language switcher can only be customized using the theme's CSS.", 'sitepress' ),
				'ICL_DONT_LOAD_LANGUAGE_SELECTOR_CSS'
			);
		}

		return $notifications;
	}
}
