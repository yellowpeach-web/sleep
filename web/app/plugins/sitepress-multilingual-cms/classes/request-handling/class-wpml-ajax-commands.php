<?php

use WPML\UrlHandling\WPLoginUrlConverter;
use WPML\AdminLanguageSwitcher\AdminLanguageSwitcher;
use WPML\Core\Component\PostHog\Application\Service\Event\EventInstanceService;

class WPML_Ajax_Commands {

	private $sitepress;

	private $wpdb;

	public function __construct( SitePress $sitepress, wpdb $wpdb ) {
		$this->sitepress = $sitepress;
		$this->wpdb      = $wpdb;
	}

	public static function add_hooks( SitePress $sitepress ) {
		global $wpdb;

		$self = new self( $sitepress, $wpdb );

		\WPML\Request\Adapter\Ajax::register( 'wpml_ajx_icl_admin_language_options', \WPML\Request\Policy\Policy::capability( 'manage_options', \WPML\Request\Policy\Authenticity::actionNonce( 'icl_admin_language_options_nonce', '_icl_nonce' ) ), array( $self, 'icl_admin_language_options' ) );
		\WPML\Request\Adapter\Ajax::register( 'wpml_ajx_icl_page_sync_options', \WPML\Request\Policy\Policy::capability( 'manage_options', \WPML\Request\Policy\Authenticity::actionNonce( 'icl_page_sync_options_nonce', '_icl_nonce' ) ), array( $self, 'icl_page_sync_options' ) );
		\WPML\Request\Adapter\Ajax::register( 'wpml_ajx_icl_login_page_translation', \WPML\Request\Policy\Policy::capability( 'manage_options', \WPML\Request\Policy\Authenticity::actionNonce( 'icl_login_page_translation_nonce', '_icl_nonce' ) ), array( $self, 'icl_login_page_translation' ) );
		\WPML\Request\Adapter\Ajax::register( 'wpml_ajx_language_domains', \WPML\Request\Policy\Policy::capability( 'manage_options', \WPML\Request\Policy\Authenticity::actionNonce( 'language_domains_nonce', '_icl_nonce' ) ), array( $self, 'language_domains' ) );
		\WPML\Request\Adapter\Ajax::register( 'wpml_ajx_dismiss_help', \WPML\Request\Policy\Policy::capability( 'manage_options', \WPML\Request\Policy\Authenticity::actionNonce( 'dismiss_help_nonce', '_icl_nonce' ) ), array( $self, 'dismiss_help' ) );
		\WPML\Request\Adapter\Ajax::register( 'wpml_ajx_toggle_show_translations', \WPML\Request\Policy\Policy::capability( 'manage_options', \WPML\Request\Policy\Authenticity::actionNonce( 'toggle_show_translations_nonce', '_icl_nonce' ) ), array( $self, 'toggle_show_translations' ) );
		\WPML\Request\Adapter\Ajax::register( 'wpml_ajx_icl_promote_form', \WPML\Request\Policy\Policy::capability( 'manage_options', \WPML\Request\Policy\Authenticity::actionNonce( 'icl_promote_form_nonce', '_icl_nonce' ) ), array( $self, 'icl_promote_form' ) );
		\WPML\Request\Adapter\Ajax::register( 'wpml_ajx_icl_st_track_strings', \WPML\Request\Policy\Policy::capability( 'manage_translations', \WPML\Request\Policy\Authenticity::actionNonce( 'icl_st_track_strings_nonce', '_icl_nonce' ) ), array( $self, 'icl_st_track_strings' ) );
		\WPML\Request\Adapter\Ajax::register( 'wpml_ajx_icl_st_more_options', \WPML\Request\Policy\Policy::capability( 'manage_translations', \WPML\Request\Policy\Authenticity::actionNonce( 'icl_st_more_options_nonce', '_icl_nonce' ) ), array( $self, 'icl_st_more_options' ) );
		\WPML\Request\Adapter\Ajax::register( 'wpml_ajx_icl_hide_languages', \WPML\Request\Policy\Policy::capability( 'manage_options', \WPML\Request\Policy\Authenticity::actionNonce( 'icl_hide_languages_nonce', '_icl_nonce' ) ), array( $self, 'icl_hide_languages' ) );
		\WPML\Request\Adapter\Ajax::register( 'wpml_ajx_icl_adjust_ids', \WPML\Request\Policy\Policy::capability( 'manage_options', \WPML\Request\Policy\Authenticity::actionNonce( 'icl_adjust_ids_nonce', '_icl_nonce' ) ), array( $self, 'icl_adjust_ids' ) );
		\WPML\Request\Adapter\Ajax::register( 'wpml_ajx_icl_automatic_redirect', \WPML\Request\Policy\Policy::capability( 'manage_options', \WPML\Request\Policy\Authenticity::actionNonce( 'icl_automatic_redirect_nonce', '_icl_nonce' ) ), array( $self, 'icl_automatic_redirect' ) );
		\WPML\Request\Adapter\Ajax::register( 'wpml_ajx_icl_custom_tax_sync_options', \WPML\Request\Policy\Policy::capability( 'manage_translations', \WPML\Request\Policy\Authenticity::actionNonce( 'icl_custom_tax_sync_options_nonce', '_icl_nonce' ) ), array( $self, 'icl_custom_tax_sync_options' ) );
		\WPML\Request\Adapter\Ajax::register( 'wpml_ajx_icl_custom_posts_sync_options', \WPML\Request\Policy\Policy::capability( 'manage_translations', \WPML\Request\Policy\Authenticity::actionNonce( 'icl_custom_posts_sync_options_nonce', '_icl_nonce' ) ), array( $self, 'icl_custom_posts_sync_options' ) );
		\WPML\Request\Adapter\Ajax::register( 'wpml_ajx_copy_from_original', \WPML\Request\Policy\Policy::capability( [ 'translate', 'manage_translations' ], \WPML\Request\Policy\Authenticity::actionNonce( 'copy_from_original_nonce', '_icl_nonce' ) ), array( $self, 'copy_from_original' ) );
		\WPML\Request\Adapter\Ajax::register( 'wpml_ajx_save_user_preferences', \WPML\Request\Policy\Policy::capability( [ 'translate', 'manage_translations' ], \WPML\Request\Policy\Authenticity::actionNonce( 'save_user_preferences_nonce', '_icl_nonce' ) ), array( $self, 'save_user_preferences' ) );
		\WPML\Request\Adapter\Ajax::register( 'wpml_ajx_wpml_cf_translation_preferences', \WPML\Request\Policy\Policy::capability( 'manage_translations', \WPML\Request\Policy\Authenticity::actionNonce( 'wpml_cf_translation_preferences_nonce', '_icl_nonce' ) ), array( $self, 'wpml_cf_translation_preferences' ) );
		\WPML\Request\Adapter\Ajax::register( 'wpml_ajx_icl_seo_options', \WPML\Request\Policy\Policy::capability( 'manage_options', \WPML\Request\Policy\Authenticity::actionNonce( 'icl_seo_options_nonce', '_icl_nonce' ) ), array( $self, 'icl_seo_options' ) );
		\WPML\Request\Adapter\Ajax::register( 'wpml_ajx_connect_translations', \WPML\Request\Policy\Policy::capability( 'edit_posts', \WPML\Request\Policy\Authenticity::actionNonce( 'connect_translations_nonce', '_icl_nonce' ) ), array( $self, 'connect_translations' ) );
		\WPML\Request\Adapter\Ajax::register( 'wpml_ajx_get_posts_from_trid', \WPML\Request\Policy\Policy::capability( 'edit_posts', \WPML\Request\Policy\Authenticity::actionNonce( 'get_posts_from_trid_nonce', '_icl_nonce' ) ), array( $self, 'get_posts_from_trid' ) );
		\WPML\Request\Adapter\Ajax::register( 'wpml_ajx_get_orphan_posts', \WPML\Request\Policy\Policy::capability( 'edit_posts', \WPML\Request\Policy\Authenticity::actionNonce( 'get_orphan_posts_nonce', '_icl_nonce' ) ), array( $self, 'get_orphan_posts' ) );
		\WPML\Request\Adapter\Ajax::register( 'wpml_ajx_icl_doc_translation_method', \WPML\Request\Policy\Policy::capability( [ 'translate', 'manage_translations' ], \WPML\Request\Policy\Authenticity::actionNonce( 'icl_doc_translation_method_nonce', '_icl_nonce' ) ), array( $self, 'icl_doc_translation_method' ) );
		\WPML\Request\Adapter\Ajax::register( 'wpml_ajx_icl_cf_translation', \WPML\Request\Policy\Policy::capability( [ 'translate', 'manage_translations' ], \WPML\Request\Policy\Authenticity::actionNonce( 'icl_cf_translation_nonce', '_icl_nonce' ) ), array( $self, 'icl_cf_translation' ) );
		\WPML\Request\Adapter\Ajax::register( 'wpml_ajx_icl_tcf_translation', \WPML\Request\Policy\Policy::capability( [ 'translate', 'manage_translations' ], \WPML\Request\Policy\Authenticity::actionNonce( 'icl_tcf_translation_nonce', '_icl_nonce' ) ), array( $self, 'icl_tcf_translation' ) );
		\WPML\Request\Adapter\Ajax::register( 'wpml_ajx_reset_duplication', \WPML\Request\Policy\Policy::capability( [ 'translate', 'manage_translations' ], \WPML\Request\Policy\Authenticity::actionNonce( 'reset_duplication_nonce', '_icl_nonce' ) ), array( $self, 'reset_duplication' ) );
		\WPML\Request\Adapter\Ajax::register( 'wpml_ajx_set_duplication', \WPML\Request\Policy\Policy::capability( [ 'translate', 'manage_translations' ], \WPML\Request\Policy\Authenticity::actionNonce( 'set_duplication_nonce', '_icl_nonce' ) ), array( $self, 'set_duplication' ) );
		\WPML\Request\Adapter\Ajax::register( 'wpml_ajx_icl_st_delete_strings', \WPML\Request\Policy\Policy::capability( [ 'translate', 'manage_translations' ], \WPML\Request\Policy\Authenticity::actionNonce( 'icl_st_delete_strings_nonce', '_icl_nonce' ) ), array( $self, 'icl_st_delete_strings' ) );
		\WPML\Request\Adapter\Ajax::register( 'wpml_ajx_icl_slug_translation', \WPML\Request\Policy\Policy::capability( [ 'translate', 'manage_translations' ], \WPML\Request\Policy\Authenticity::actionNonce( 'icl_slug_translation_nonce', '_icl_nonce' ) ), array( $self, 'icl_slug_translation' ) );

		\WPML\Request\Adapter\Ajax::register( 'wpml_health_check', \WPML\Request\Policy\Policy::authenticated( \WPML\Request\Policy\Authenticity::actionNonce( 'wpml_health_check', '_health_nonce' ), 'diagnostic ping printed on every admin screen for every logged-in user; answers an empty 200 and discloses nothing, and the administrator-only flag write is authorized inside the handler' ), array( $self, 'health_check' ) );
	}

	public static function is_authenticated_command( $command ) {
		$nonce = isset( $_POST['_icl_nonce'] ) && is_string( $_POST['_icl_nonce'] ) ? wp_unslash( $_POST['_icl_nonce'] ) : '';

		return '' !== $nonce && (bool) wp_verify_nonce( $nonce, $command . '_nonce' );
	}

	private function authenticate_or_exit( $command ) {
		if ( \WPML\Setup\Initializer::settingsAreUnrecoverable() ) {
			wp_send_json_error( \WPML\Setup\Initializer::getSettingsRecoveryError() );
		}

		if ( ! self::is_authenticated_command( $command ) ) {
			wp_die( 'Invalid request authenticity token', 403 );
		}
	}

	private function user_is_admin_or_exit() {
		if ( ! WPML\LIB\WP\User::currentUserIsAdmin() ) {
			wp_die( 'Unauthorized', 403 );
		}
	}

	private function user_is_manager_or_exit() {
		if ( ! WPML\LIB\WP\User::currentUserIsTranslationManagerOrHigher() ) {
			wp_die( 'Unauthorized', 403 );
		}
	}

	private function user_is_translator_or_exit() {
		if ( ! WPML\LIB\WP\User::currentUserIsTranslatorOrHigher() ) {
			wp_die( 'Unauthorized', 403 );
		}
	}

	private function user_can_edit_post_or_exit( $post_id = null ) {
		$is_allowed = null !== $post_id
			? current_user_can( 'edit_post', (int) $post_id )
			: current_user_can( 'edit_posts' );
		if ( ! $is_allowed ) {
			wp_die( 'Unauthorized', 403 );
		}
	}


	private function post_has( $key ) {
		return isset( $_POST[ $key ] );
	}

	private function post_int( $key, $default = 0 ) {
		return isset( $_POST[ $key ] ) && is_scalar( $_POST[ $key ] ) ? (int) $_POST[ $key ] : $default;
	}

	private function post_text( $key, $default = '' ) {
		return isset( $_POST[ $key ] ) && is_scalar( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( (string) $_POST[ $key ] ) ) : $default;
	}

	private function post_key( $key, $default = '' ) {
		return isset( $_POST[ $key ] ) && is_scalar( $_POST[ $key ] ) ? sanitize_key( wp_unslash( (string) $_POST[ $key ] ) ) : $default;
	}

	private function post_array( $key, array $default = [] ) {
		if ( ! isset( $_POST[ $key ] ) || ! is_array( $_POST[ $key ] ) ) {
			return $default;
		}

		return self::sanitize_text_deep( wp_unslash( $_POST[ $key ] ) );
	}

	private static function sanitize_text_deep( $value ) {
		if ( is_array( $value ) ) {
			$clean = [];
			foreach ( $value as $k => $v ) {
				$clean[ is_string( $k ) ? sanitize_text_field( $k ) : $k ] = self::sanitize_text_deep( $v );
			}

			return $clean;
		}

		return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
	}

	private static function selected_user_role_keys( array $users, array $editable_roles ) {
		return array_keys( array_intersect_key( $users, $editable_roles ) );
	}

	private function get_var_prepared( $sql, array $args ) {
		$prepared = $this->wpdb->prepare( $sql, $args );

		return $this->wpdb->get_var( $prepared );
	}

	public function icl_admin_language_options() {
		$this->authenticate_or_exit( 'icl_admin_language_options' );
		$this->user_is_admin_or_exit();

		$iclsettings                            = $this->sitepress->get_settings();
		$iclsettings['admin_default_language'] = $this->post_text( 'icl_admin_default_language' );
		$this->sitepress->save_settings( $iclsettings );
		echo 1;
		exit;
	}

	public function icl_page_sync_options() {
		$this->authenticate_or_exit( 'icl_page_sync_options' );
		$this->user_is_admin_or_exit();

		$iclsettings                                = $this->sitepress->get_settings();
		$iclsettings['sync_page_ordering']          = $this->post_int( 'icl_sync_page_ordering' );
		$iclsettings['sync_page_parent']            = $this->post_int( 'icl_sync_page_parent' );
		$iclsettings['sync_page_template']          = $this->post_int( 'icl_sync_page_template' );
		$iclsettings['sync_comment_status']         = $this->post_int( 'icl_sync_comment_status' );
		$iclsettings['sync_ping_status']            = $this->post_int( 'icl_sync_ping_status' );
		$iclsettings['sync_sticky_flag']            = $this->post_int( 'icl_sync_sticky_flag' );
		$iclsettings['sync_password']               = $this->post_int( 'icl_sync_password' );
		$iclsettings['sync_private_flag']           = $this->post_int( 'icl_sync_private_flag' );
		$iclsettings['sync_post_format']            = $this->post_int( 'icl_sync_post_format' );
		if ( $this->post_has( 'icl_sync_delete' ) ) {
			$iclsettings['sync_delete'] = $this->post_int( 'icl_sync_delete' );
		}
		if ( $this->post_has( 'icl_sync_delete_tax' ) ) {
			$iclsettings['sync_delete_tax'] = $this->post_int( 'icl_sync_delete_tax' );
		}
		$iclsettings['sync_post_taxonomies']        = $this->post_int( 'icl_sync_post_taxonomies' );
		$iclsettings['sync_post_date']              = $this->post_int( 'icl_sync_post_date' );
		$iclsettings['sync_comments_on_duplicates'] = $this->post_int( 'icl_sync_comments_on_duplicates' );
		$this->sitepress->save_settings( $iclsettings );

		$wpml_page_builder_options = new WPML_Page_Builder_Settings();

		if ( $this->post_has( 'wpml_pb_translate_raw_html' ) ) {
			$wpml_page_builder_options->set_raw_html_translatable(
				filter_var( $this->post_text( 'wpml_pb_translate_raw_html' ), FILTER_VALIDATE_INT )
			);
		} else {
			$wpml_page_builder_options->set_raw_html_translatable( 0 );
		}

		$wpml_page_builder_options->save();

		echo 1;
		exit;
	}

	public function icl_login_page_translation() {
		$this->authenticate_or_exit( 'icl_login_page_translation' );
		$this->user_is_admin_or_exit();

		$translateLoginPageIsEnabled = get_option( WPLoginUrlConverter::SETTINGS_KEY );
		if ( ! $translateLoginPageIsEnabled && filter_input( INPUT_POST, 'login_page_translation', FILTER_VALIDATE_BOOLEAN ) ) {
			AdminLanguageSwitcher::enable();
		}

		WPLoginUrlConverter::saveState(
			(bool) filter_input( INPUT_POST, 'login_page_translation', FILTER_VALIDATE_INT )
		);

		AdminLanguageSwitcher::saveState(
			(bool) filter_input( INPUT_POST, 'show_login_page_language_switcher', FILTER_VALIDATE_INT )
		);

		echo 1;
		exit;
	}

	public function language_domains() {
		$this->authenticate_or_exit( 'language_domains' );
		$this->user_is_admin_or_exit();

		$language_domains_helper = new WPML_Lang_Domains_Box( $this->sitepress );
		echo $language_domains_helper->render();
		exit;
	}

	public function dismiss_help() {
		$this->authenticate_or_exit( 'dismiss_help' );
		$this->user_is_admin_or_exit();

		icl_set_setting( 'dont_show_help_admin_notice', true );
		icl_save_settings();
		exit;
	}

	public function toggle_show_translations() {
		$this->authenticate_or_exit( 'toggle_show_translations' );
		$this->user_is_admin_or_exit();

		icl_set_setting( 'show_translations_flag', intval( ! wpml_get_setting( 'show_translations_flag', true ) ) );
		icl_save_settings();
		exit;
	}

	public function icl_promote_form() {
		$this->authenticate_or_exit( 'icl_promote_form' );
		$this->user_is_admin_or_exit();

		icl_set_setting( 'promote_wpml', $this->post_int( 'icl_promote' ) );
		icl_save_settings();
		echo '1|';
		exit;
	}

	public function icl_st_track_strings() {
		$this->authenticate_or_exit( 'icl_st_track_strings' );
		$this->user_is_manager_or_exit();

		$iclsettings = $this->sitepress->get_settings();
		$st_options  = $this->post_array( 'icl_st' );
		foreach ( $st_options as $k => $v ) {
			$iclsettings['st'][ $k ] = $v;
		}
		if ( array_key_exists( 'st', $iclsettings ) && array_key_exists( 'hl_color', $iclsettings['st'] ) && ! wpml_is_valid_hex_color( $iclsettings['st']['hl_color'] ) ) {
			$iclsettings['st']['hl_color'] = '#FFFF00';
		}
		$this->sitepress->save_settings( $iclsettings );

		do_action( 'wpml_st_strings_tracking_option_saved', isset( $st_options['track_strings'] ) ? (int) $st_options['track_strings'] : 0 );

		echo 1;
		exit;
	}

	public function icl_st_more_options() {
		$this->authenticate_or_exit( 'icl_st_more_options' );
		$this->user_is_manager_or_exit();

		global $sitepress_settings;

		$users                                 = $this->post_array( 'users' );
		$iclsettings                           = $this->sitepress->get_settings();
		$iclsettings['st']['translated-users'] = self::selected_user_role_keys( $users, get_editable_roles() );
		$this->sitepress->save_settings( $iclsettings );
		if ( ! empty( $iclsettings['st']['translated-users'] ) && function_exists( 'icl_st_register_user_strings_all' ) ) {
			$sitepress_settings['st']['translated-users'] = $iclsettings['st']['translated-users'];
			icl_st_register_user_strings_all();
		}
		echo 1;
		exit;
	}

	public function icl_hide_languages() {
		$this->authenticate_or_exit( 'icl_hide_languages' );
		$this->user_is_admin_or_exit();

		$iclsettings                     = $this->sitepress->get_settings();
		$iclsettings['hidden_languages'] = $this->post_array( 'icl_hidden_languages' );
		$this->sitepress->set_setting( 'hidden_languages', [] );
		$active_languages = $this->sitepress->get_active_languages();
		if ( ! empty( $iclsettings['hidden_languages'] ) ) {
			if ( 1 === count( $iclsettings['hidden_languages'] ) ) {
				$out = sprintf(
					/* translators: Notice saying that one language is kept from visitors. %s: the name of that language. */
					__( '%s is currently hidden to visitors.', 'sitepress' ),
					$active_languages[ $iclsettings['hidden_languages'][0] ]['display_name']
				);
			} else {
				$_hlngs = [];
				foreach ( $iclsettings['hidden_languages'] as $l ) {
					$_hlngs[] = $active_languages[ $l ]['display_name'];
				}
				$hlangs = join( ', ', $_hlngs );
				/* translators: Notice saying that several languages are kept from visitors. %s: the names of those languages, separated by commas. */
				$out = sprintf( __( '%s are currently hidden to visitors.', 'sitepress' ), $hlangs );
			}
			$out .= ' ' . sprintf(
				/* translators: Note shown after languages were hidden from visitors. "its/their" covers one hidden language or several. %s: the address of the user's own profile page, inside the link tag that is already in the text. */
				__( 'You can enable its/their display for yourself, in your <a href="%s">profile page</a>.', 'sitepress' ),
				'profile.php#wpml'
			);
		} else {
			$out = __( 'All languages are currently displayed.', 'sitepress' );
		}
		$this->sitepress->save_settings( $iclsettings );
		echo '1|' . wp_kses_post( $out );
		exit;
	}

	public function icl_adjust_ids() {
		$this->authenticate_or_exit( 'icl_adjust_ids' );
		$this->user_is_admin_or_exit();

		$iclsettings                    = $this->sitepress->get_settings();
		$iclsettings['auto_adjust_ids'] = $this->post_int( 'icl_adjust_ids' );
		$this->sitepress->save_settings( $iclsettings );
		echo '1|';
		exit;
	}

	public function icl_automatic_redirect() {
		$this->authenticate_or_exit( 'icl_automatic_redirect' );
		$this->user_is_admin_or_exit();

		$remember_language = $this->post_int( 'icl_remember_language' );
		if ( $remember_language < 24 ) {
			$remember_language = 24;
		}
		$iclsettings                       = $this->sitepress->get_settings();
		$iclsettings['automatic_redirect'] = $this->post_int( 'icl_automatic_redirect' );
		$iclsettings['remember_language']  = $remember_language;
		$this->sitepress->save_settings( $iclsettings );
		echo '1|';
		exit;
	}

	public function icl_custom_tax_sync_options() {
		$this->authenticate_or_exit( 'icl_custom_tax_sync_options' );
		$this->user_is_manager_or_exit();

		$new_options      = $this->post_array( 'icl_sync_tax' );
		$unlocked_options = $this->post_array( 'icl_sync_tax_unlocked' );
		$settings_helper = wpml_load_settings_helper();

		$previous_unlocked = $this->sitepress->get_setting( 'taxonomies_unlocked_option', [] );

		$settings_helper->update_taxonomy_unlocked_settings( $unlocked_options );
		$settings_helper->update_taxonomy_sync_settings( $new_options );

		foreach ( $unlocked_options as $slug => $is_unlocked ) {
			$was_previously_unlocked = isset( $previous_unlocked[ $slug ] ) ? (int) $previous_unlocked[ $slug ] : 0;
			$is_now_unlocked         = (int) $is_unlocked;

			if ( $is_now_unlocked === 1 && $was_previously_unlocked === 0 ) {
				$taxonomy_object = get_taxonomy( $slug );

				if ( $taxonomy_object ) {
					$event_props = [
						'type'          => 'taxonomy',
						'slug'          => $slug,
						'name'          => isset( $taxonomy_object->label ) ? $taxonomy_object->label : $slug,
						'singular_name' => isset( $taxonomy_object->labels->singular_name ) ? $taxonomy_object->labels->singular_name : $slug,
					];

					\WPML\PostHog\Event\CaptureEvent::capture(
						( new EventInstanceService() )->getTaxonomyUnlockedEvent( $event_props )
					);
				}
			}
		}

		echo '1|';
		exit;
	}

	public function icl_custom_posts_sync_options() {
		$this->authenticate_or_exit( 'icl_custom_posts_sync_options' );
		$this->user_is_manager_or_exit();

		$new_options      = $this->post_array( 'icl_sync_custom_posts' );
		$unlocked_options = $this->post_array( 'icl_sync_custom_posts_unlocked' );
		$settings_helper = wpml_load_settings_helper();

		$previous_unlocked = $this->sitepress->get_setting( 'custom_posts_unlocked_option', [] );

		$settings_helper->update_cpt_unlocked_settings( $unlocked_options );
		$settings_helper->update_cpt_sync_settings( $new_options );

		foreach ( $unlocked_options as $slug => $is_unlocked ) {
			$was_previously_unlocked = isset( $previous_unlocked[ $slug ] ) ? (int) $previous_unlocked[ $slug ] : 0;
			$is_now_unlocked         = (int) $is_unlocked;

			if ( $is_now_unlocked === 1 && $was_previously_unlocked === 0 ) {
				$post_type_object = get_post_type_object( $slug );

				if ( $post_type_object ) {
					$event_props = [
						'type'          => 'post_type',
						'slug'          => $slug,
						'name'          => isset( $post_type_object->labels->name ) ? $post_type_object->labels->name : $slug,
						'singular_name' => isset( $post_type_object->labels->singular_name ) ? $post_type_object->labels->singular_name : $slug,
					];

					\WPML\PostHog\Event\CaptureEvent::capture(
						( new EventInstanceService() )->getPostTypeUnlockedEvent( $event_props )
					);
				}
			}
		}

		echo '1|';
		exit;
	}

	public function copy_from_original() {
		$this->authenticate_or_exit( 'copy_from_original' );
		$this->user_is_translator_or_exit();

		$content_type = filter_input( INPUT_POST, 'content_type' );
		$excerpt_type = filter_input( INPUT_POST, 'excerpt_type' );
		$trid         = filter_input( INPUT_POST, 'trid' );
		$lang         = filter_input( INPUT_POST, 'lang' );

		$copy_source_post_id = (int) $this->get_var_prepared(
			"SELECT element_id FROM {$this->wpdb->prefix}icl_translations WHERE trid=%d AND language_code=%s",
			[ (int) $trid, $lang ]
		);
		if ( ! $copy_source_post_id || ! current_user_can( 'read_post', $copy_source_post_id ) ) {
			wp_die( 'Unauthorized', 403 );
		}

		$target_post = (int) filter_input( INPUT_POST, 'target_post_id', FILTER_SANITIZE_NUMBER_INT );
		$target_lang = $this->copy_target_language( $target_post );
		echo wp_json_encode( WPML_Post_Edit_Ajax::copy_from_original_fields( $content_type, $excerpt_type, $trid, $lang, $target_post, $target_lang ) );
		exit;
	}

	private function copy_target_language( $target_post ) {
		if ( ! $target_post ) {
			return null;
		}

		$active = $this->sitepress->get_active_languages();
		$posted = filter_input( INPUT_POST, 'target_lang' );

		if ( is_string( $posted ) && array_key_exists( $posted, $active ) ) {
			return $posted;
		}

		$stored = $this->sitepress->get_language_for_element( $target_post, 'post_' . get_post_type( $target_post ) );

		return is_string( $stored ) && array_key_exists( $stored, $active ) ? $stored : null;
	}

	public function save_user_preferences() {
		$this->authenticate_or_exit( 'save_user_preferences' );
		$this->user_is_translator_or_exit();

		$user_preferences = $this->sitepress->get_user_preferences();
		$this->sitepress->set_user_preferences( array_merge_recursive( $user_preferences, $this->post_array( 'user_preferences' ) ) );
		$this->sitepress->save_user_preferences();
		exit;
	}

	public function wpml_cf_translation_preferences() {
		$this->authenticate_or_exit( 'wpml_cf_translation_preferences' );
		$this->user_is_manager_or_exit();

		$custom_field = $this->post_text( WPML_POST_META_SETTING_INDEX_SINGULAR );
		if ( empty( $custom_field ) ) {
			echo '<span style="color:#FF0000;">'
				 . esc_html__( 'Error: No custom field', 'sitepress' ) . '</span>';
			die();
		}
		if ( ! $this->post_has( 'translate_action' ) ) {
			echo '<span style="color:#FF0000;">'
				 . esc_html__( 'Error: Please provide translation action', 'sitepress' ) . '</span>';
			die();
		}
		$translate_action = $this->post_int( 'translate_action' );
		if ( defined( 'WPML_TM_VERSION' ) ) {
			global $iclTranslationManagement;
			if ( ! empty( $iclTranslationManagement ) ) {
				$iclTranslationManagement->settings[ WPML_POST_META_SETTING_INDEX_PLURAL ][ $custom_field ] = $translate_action;
				$iclTranslationManagement->save_settings();
				echo '<strong><em>' . /* translators: Notice shown after the translation setting of one field is saved. */ esc_html__( 'Settings updated', 'sitepress' ) . '</em></strong>';
			} else {
				echo '<span style="color:#FF0000;">'
					 . esc_html__( 'Error: WPML Translation Management plugin not initiated', 'sitepress' )
					 . '</span>';
			}
		} else {
			echo '<span style="color:#FF0000;">'
				 . esc_html__( 'Error: Please activate WPML Translation Management plugin', 'sitepress' )
				 . '</span>';
		}
		exit;
	}

	public function icl_seo_options() {
		$this->authenticate_or_exit( 'icl_seo_options' );
		$this->user_is_admin_or_exit();

		$seo = $this->sitepress->get_setting( 'seo', [] );

		$seo['head_langs']                  = $this->post_int( 'icl_seo_head_langs' );
		$seo['canonicalization_duplicates'] = $this->post_int( 'icl_seo_canonicalization_duplicates' );
		$seo['head_langs_priority']         = $this->post_int( 'wpml_seo_head_langs_priority', 1 );

		$this->sitepress->set_setting( 'seo', $seo, true );
		echo '1|';
		exit;
	}

	public function connect_translations() {
		$this->authenticate_or_exit( 'connect_translations' );
		$this->user_can_edit_post_or_exit( $this->post_int( 'post_id' ) );

		$new_trid      = $this->post_int( 'new_trid' );
		$post_type     = $this->post_key( 'post_type' );
		$post_id       = $this->post_int( 'post_id' );
		$set_as_source = (bool) $this->post_int( 'set_as_source' );
		$element_type  = 'post_' . $post_type;

		$connect = new \WPML\Translation\ConnectTranslations( $this->wpdb, $this->sitepress );
		$state   = $connect->load( $post_id, $post_type, $new_trid );

		$this->refuse_if_error( $connect->refuseTarget( $state ) );

		$new_trid_original_id = $connect->targetOriginalId( $state );
		if ( $new_trid_original_id ) {
			$this->user_can_edit_post_or_exit( $new_trid_original_id );
		} else {
			foreach ( $connect->targetElementIds( $state ) as $target_element_id ) {
				$this->user_can_edit_post_or_exit( $target_element_id );
			}
		}

		$this->refuse_if_error( $connect->refuseMove( $state, $set_as_source ) );
		$this->refuse_if_error( $connect->apply( $state, $set_as_source ) );

		if ( $set_as_source ) {
			do_action(
				'wpml_translation_update',
				[
					'type'         => 'update',
					'trid'         => $new_trid,
					'element_type' => $element_type,
					'context'      => 'post',
				]
			);

			do_action(
				'wpml_translations_connected',
				[
					'post_id'       => (int) $post_id,
					'post_type'     => (string) $post_type,
					'new_trid'      => (int) $new_trid,
					'element_type'  => $element_type,
					'set_as_source' => true,
				]
			);
		} else {
			do_action(
				'wpml_translation_update',
				[
					'type'         => 'update',
					'trid'         => $new_trid,
					'element_id'   => $post_id,
					'element_type' => $element_type,
					'context'      => 'post',
				]
			);

			do_action(
				'wpml_translations_connected',
				[
					'post_id'       => (int) $post_id,
					'post_type'     => (string) $post_type,
					'new_trid'      => (int) $new_trid,
					'element_type'  => $element_type,
					'set_as_source' => false,
				]
			);
		}
		echo wp_json_encode( true );
		exit;
	}

	private function refuse_if_error( $refusal ) {
		if ( $refusal instanceof \WP_Error ) {
			\WPML\Request\Payload::refuse( $refusal );
		}
	}

	public function get_posts_from_trid() {
		$this->authenticate_or_exit( 'get_posts_from_trid' );
		$this->user_can_edit_post_or_exit();

		$trid      = $this->post_int( 'trid' );
		$post_type = $this->post_key( 'post_type' );

		$requested_group_original_id = (int) $this->get_var_prepared(
			"SELECT element_id FROM {$this->wpdb->prefix}icl_translations WHERE trid = %d AND element_type = %s AND source_language_code IS NULL LIMIT 1",
			[ $trid, 'post_' . $post_type ]
		);
		if ( $requested_group_original_id ) {
			$this->user_can_edit_post_or_exit( $requested_group_original_id );
		}

		$translations = $this->sitepress->get_element_translations( $trid, 'post_' . $post_type );

		$results = [];
		foreach ( $translations as $language_code => $translation ) {
			if (
				! current_user_can( 'read_post', $translation->element_id )
				&& ! current_user_can( 'edit_post', $translation->element_id )
			) {
				continue;
			}
			$post = get_post( $translation->element_id );
			if ( ! $post ) {
				continue;
			}
			$title                = $post->post_title ? $post->post_title : strip_shortcodes( wp_trim_words( $post->post_content, 50 ) );
			$source_language_code = $translation->source_language_code;
			$results[]            = (object) [
				'language'        => $language_code,
				'title'           => $title,
				'source_language' => $source_language_code,
			];
		}
		echo wp_json_encode( $results );
		exit;
	}

	public function get_orphan_posts() {
		$this->authenticate_or_exit( 'get_orphan_posts' );
		$this->user_can_edit_post_or_exit();

		$trid            = $this->post_int( 'trid' );
		$post_type       = $this->post_key( 'post_type' );
		$source_language = $this->post_text( 'source_language' );

		$current_group_member_id = (int) $this->get_var_prepared(
			"SELECT element_id FROM {$this->wpdb->prefix}icl_translations WHERE trid = %d AND element_type = %s LIMIT 1",
			[ $trid, 'post_' . $post_type ]
		);
		if ( ! $current_group_member_id ) {
			echo wp_json_encode( [] );
			exit;
		}
		$this->user_can_edit_post_or_exit( $current_group_member_id );

		$results = $this->sitepress->get_orphan_translations( $trid, $post_type, $source_language, true );

		echo wp_json_encode( $results );
		exit;
	}

	public function icl_doc_translation_method() {
		$this->authenticate_or_exit( 'icl_doc_translation_method' );
		$this->user_is_translator_or_exit();
		do_action( 'icl_ajx_custom_call', 'icl_doc_translation_method', $_REQUEST );
		exit;
	}

	public function icl_cf_translation() {
		$this->authenticate_or_exit( 'icl_cf_translation' );
		$this->user_is_translator_or_exit();
		do_action( 'icl_ajx_custom_call', 'icl_cf_translation', $_REQUEST );
		exit;
	}

	public function icl_tcf_translation() {
		$this->authenticate_or_exit( 'icl_tcf_translation' );
		$this->user_is_translator_or_exit();
		do_action( 'icl_ajx_custom_call', 'icl_tcf_translation', $_REQUEST );
		exit;
	}

	public function reset_duplication() {
		$this->authenticate_or_exit( 'reset_duplication' );
		$this->user_is_translator_or_exit();

		$this->user_can_edit_post_or_exit( $this->post_int( 'post_id' ) );

		do_action( 'icl_ajx_custom_call', 'reset_duplication', $_REQUEST );
		exit;
	}

	public function set_duplication() {
		$this->authenticate_or_exit( 'set_duplication' );
		$this->user_is_translator_or_exit();

		$duplication_original_id = $this->post_int( 'wpml_original_post_id' );
		$this->user_can_edit_post_or_exit( $duplication_original_id );

		$duplication_target_lang = $this->post_text( 'post_lang' );
		$duplication_target_id   = (int) $this->get_var_prepared(
			"SELECT target.element_id
			 FROM {$this->wpdb->prefix}icl_translations source
			 JOIN {$this->wpdb->prefix}icl_translations target
				ON target.trid = source.trid
			 WHERE source.element_id = %d
				AND source.element_type LIKE %s
				AND target.language_code = %s",
			[ $duplication_original_id, $this->wpdb->esc_like( 'post_' ) . '%', $duplication_target_lang ]
		);
		if ( $duplication_target_id && $duplication_target_id !== $duplication_original_id ) {
			$this->user_can_edit_post_or_exit( $duplication_target_id );
		}

		do_action( 'icl_ajx_custom_call', 'set_duplication', $_REQUEST );
		exit;
	}

	public function icl_st_delete_strings() {
		$this->authenticate_or_exit( 'icl_st_delete_strings' );
		$this->user_is_translator_or_exit();
		do_action( 'icl_ajx_custom_call', 'icl_st_delete_strings', $_REQUEST );
		exit;
	}

	public function icl_slug_translation() {
		$this->authenticate_or_exit( 'icl_slug_translation' );
		$this->user_is_translator_or_exit();
		do_action( 'icl_ajx_custom_call', 'icl_slug_translation', $_REQUEST );
		exit;
	}

	public function health_check() {
		$health_nonce = isset( $_POST['_health_nonce'] ) && is_string( $_POST['_health_nonce'] ) ? wp_unslash( $_POST['_health_nonce'] ) : '';

		if (
			! \WPML\Setup\Initializer::settingsAreUnrecoverable()
			&& 'POST' === strtoupper( (string) filter_input( INPUT_SERVER, 'REQUEST_METHOD' ) )
			&& '' !== $health_nonce
			&& wp_verify_nonce( $health_nonce, 'wpml_health_check' )
			&& is_user_logged_in()
			&& current_user_can( 'manage_options' )
		) {
			icl_set_setting( 'ajx_health_checked', true, true );
		}
		exit;
	}
}
