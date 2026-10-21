<?php

use WPML\Language\Detection\CookieLanguage;
use WPML\LIB\WP\Hooks;
use WPML\LIB\WP\Option;
use WPML\UIPage;
use WPML\UrlHandling\WPLoginUrlConverter;
use function WPML\Container\make;
use function WPML\FP\spreadArgs;

class WPML_User_Language {
	protected $sitepress;

	private $email_language_frames = array();

	private $hooks_registered = false;

	private $users_with_changed_locale = array();

	private $wpdb;

	public function __construct( SitePress $sitepress, ?wpdb $wpdb = null ) {
		$this->sitepress = $sitepress;

		if ( ! $wpdb ) {
			global $wpdb;
		}
		$this->wpdb = $wpdb;
	}

	public function register_hooks() {
		if ( $this->hooks_registered ) {
			return;
		}
		$this->hooks_registered = true;

		Hooks::onAction( 'wp_login', 10, 2 )
		     ->then( spreadArgs( [ $this, 'update_user_lang_from_login' ] ) );

		Hooks::onAction( 'wpml_user_profile_options' )
		     ->then( [ $this, 'show_ui_to_enable_login_translation' ] );

		add_action( 'wpml_switch_language_for_email', array( $this, 'switch_language_for_email_action' ), 10, 1 );
		add_action( 'wpml_restore_language_from_email', array( $this, 'restore_language_from_email_action' ), 10, 0 );
		add_action( 'added_user_meta', array( $this, 'remember_added_locale' ), 10, 4 );
		add_action( 'updated_user_meta', array( $this, 'remember_locale_change' ), 10, 3 );
		add_action( 'deleted_user_meta', array( $this, 'remember_locale_change' ), 10, 3 );
		add_action( 'profile_update', array( $this, 'sync_admin_user_language_action' ), 10, 1 );
		add_action( 'wp_update_user', array( $this, 'clear_user_admin_language_cache_on_wp_update_user' ), 10, 1 );

		if ( $this->is_editing_current_profile() || $this->is_editing_other_profile() ) {
			add_filter( 'get_available_languages', array( $this, 'intersect_wpml_wp_languages' ) );
		}
	}

	public function intersect_wpml_wp_languages( $wp_languages ) {
		$active_wpml_languages         = wp_list_pluck( $this->sitepress->get_active_languages(), 'default_locale' );
		$active_wpml_codes             = array_flip( $active_wpml_languages );
		$intersect_languages_by_locale = array_intersect( $active_wpml_languages, $wp_languages );
		$intersect_languages_by_code   = array_intersect( $active_wpml_codes, $wp_languages );

		return array_merge( $intersect_languages_by_code, $intersect_languages_by_locale );
	}

	public function switch_language_for_email_action( $email ) {
		$this->switch_language_for_email( $email );
	}

	private function switch_language_for_email( $email ) {
		$language = apply_filters( 'wpml_user_language', null, $email );

		$frame = array(
			'changed'    => false,
			'switched'   => false,
			'admin_lang' => null,
		);

		if ( $language ) {
			$current_language    = $this->sitepress->get_current_language();
			$frame['admin_lang'] = $this->sitepress->get_admin_language();
			$frame['changed']    = $language !== $current_language || $language !== $frame['admin_lang'];
		}

		$this->email_language_frames[] = $frame;
		$key                           = count( $this->email_language_frames ) - 1;

		if ( $frame['changed'] ) {
			$this->sitepress->switch_lang( $language, false );

			$this->email_language_frames[ $key ]['switched'] = true;

			$this->sitepress->set_admin_language( $language );
		}
	}

	public function restore_language_from_email_action() {
		$this->wpml_restore_language_from_email();
	}

	private function wpml_restore_language_from_email() {
		if ( ! $this->email_language_frames ) {
			return;
		}

		$frame = array_pop( $this->email_language_frames );

		if ( ! empty( $frame['switched'] ) ) {
			$this->sitepress->switch_lang();
		}

		if ( $frame['changed'] ) {
			$this->sitepress->set_admin_language( $frame['admin_lang'] );
		}
	}

	public function remember_added_locale( $meta_id, $user_id, $meta_key, $value ) {
		if ( 'locale' !== $meta_key ) {
			return;
		}

		if ( is_scalar( $value ) && '' !== (string) $value ) {
			$this->remember_locale_change( $meta_id, $user_id, $meta_key );
		}
	}

	public function remember_locale_change( $meta_id, $user_id, $meta_key ) {
		if ( 'locale' === $meta_key ) {
			$this->users_with_changed_locale[ (int) $user_id ] = true;
		}
	}

	public function sync_admin_user_language_action( $user_id ) {
		if ( ! $this->consume_locale_change( $user_id ) ) {
			return;
		}

		if ( $this->user_needs_sync_admin_lang() ) {
			$this->sync_admin_user_language( $user_id );
		}
	}

	private function consume_locale_change( $user_id ) {
		$user_id = (int) $user_id;

		if ( empty( $this->users_with_changed_locale[ $user_id ] ) ) {
			return false;
		}

		unset( $this->users_with_changed_locale[ $user_id ] );

		return true;
	}

	public function clear_user_admin_language_cache_on_wp_update_user( $user_id ) {
		wp_cache_delete( $user_id, WPML_User_Admin_Language::CACHE_GROUP );
	}

	public function sync_default_admin_user_languages() {
		$wpdb     = $this->wpdb;
		$user_ids = $wpdb->get_col(
			$wpdb->prepare( "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s", 'locale', '' )
		);

		if ( $user_ids ) {
			$language = $this->sitepress->get_default_language();

			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->usermeta} SET meta_value = %s WHERE meta_key = %s AND user_id IN (" . implode( ', ', array_fill( 0, count( $user_ids ), '%d' ) ) . ')',
					array_merge( [ $language, 'icl_admin_language' ], array_map( 'intval', $user_ids ) )
				)
			);

			if ( is_array( $user_ids ) ) {
				foreach ( $user_ids as $user_id ) {
					$this->flush_user_language_cache( $user_id );
				}
			}
		} else {
			$this->flush_user_language_cache();
		}
	}

	private function sync_admin_user_language( $user_id ) {
		$wp_language = get_user_meta( $user_id, 'locale', true );

		if ( $wp_language ) {
			$user_language = $this->select_language_code_from_locale( $wp_language );
		} else {
			$user_language = $this->sitepress->get_default_language();
		}
		update_user_meta( $user_id, 'icl_admin_language', $user_language );
	}

	private function select_language_code_from_locale( $wp_locale ) {
		$code = $this->sitepress->get_language_code_from_locale( $wp_locale );

		if ( ! $code ) {
			$guess_code   = strtolower( substr( $wp_locale, 0, 2 ) );
			$guess_locale = $this->sitepress->get_locale_from_language_code( $guess_code );

			if ( $guess_locale ) {
				$code = $guess_code;
			}
		}

		return $code;
	}

	private function user_needs_sync_admin_lang() {
		$wp_api = $this->sitepress->get_wp_api();

		return $wp_api->version_compare_naked( get_bloginfo( 'version' ), '4.7', '>=' );
	}

	private function is_editing_current_profile() {
		global $pagenow;

		return isset( $pagenow ) && 'profile.php' === $pagenow;
	}

	private function is_editing_other_profile() {
		global $pagenow;

		return isset( $pagenow ) && 'user-edit.php' === $pagenow;
	}

	public function update_user_lang_on_site_setup() {
		$current_user_id = get_current_user_id();
		$wp_user_lang    = get_user_meta( $current_user_id, 'locale', true );

		if ( ! $wp_user_lang ) {
			return;
		}

		$lang_code_from_locale = $this->select_language_code_from_locale( $wp_user_lang );
		$wpml_user_lang        = get_user_meta( $current_user_id, 'icl_admin_language', true );

		if ( $current_user_id && $lang_code_from_locale && ! $wpml_user_lang ) {
			update_user_meta( $current_user_id, 'icl_admin_language', $lang_code_from_locale );
		}
	}

	public function update_user_lang_from_login( $username, $user = null ) {
		$cookieName = 'wp-wpml_login_lang';

		$cookieLanguage = make( CookieLanguage::class, [ ':defaultLanguage' => '' ] );
		$loginLanguage  = $cookieLanguage->get( $cookieName );

		if ( $loginLanguage ) {
			$this->seed_browsing_language( $cookieLanguage, $loginLanguage );
		}

		$secure = ( 'https' === parse_url( wp_login_url(), PHP_URL_SCHEME ) );
		setcookie( $cookieName, '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, $secure );
	}

	private function seed_browsing_language( CookieLanguage $cookieLanguage, $languageCode ) {
		$cookie = make( WPML_Cookie::class );

		$expires = time() + DAY_IN_SECONDS;
		$path    = defined( 'COOKIEPATH' ) ? COOKIEPATH : '/';
		$domain  = $cookieLanguage->get_cookie_domain();

		$names = [
			$cookieLanguage->getFrontendCookieName(),
			$cookieLanguage->getBackendCookieName(),
		];

		foreach ( $names as $name ) {
			$cookie->set_cookie( $name, $languageCode, $expires, $path, $domain );
			$_COOKIE[ $name ] = $languageCode;
		}
	}

	public function show_ui_to_enable_login_translation() {
		if ( current_user_can( 'manage_options' ) && ! WPLoginUrlConverter::isEnabled() ) {

			$settingsPage     = UIPage::getSettings() . '#ml-content-setup-sec-wp-login';
			/* translators: Link text that opens the WPML settings screen. It is the path through the menu, so keep the arrow and translate the two names as they appear in the menu. */
			$settingsPageLink = '<a href="' . $settingsPage . '">' . __( 'WPML->Settings', 'sitepress' ) . '</a>';
			// translators: %s link to WPML Settings page
			$message = esc_html__( 'WPML will include a language switcher on the WordPress login page. To change this, go to %s.', 'sitepress' );
			?>
			<tr class="user-language-wrap">
				<th><?php /* translators: Label in front of the setting that says in which language the login screen is shown. */ esc_html_e( 'Login Page:', 'sitepress' ); ?></th>
				<td>
					<?php wp_nonce_field( 'icl_login_page_translation_nonce', 'icl_login_page_translation_nonce' ); ?>
					<div id="wpml-login-translation">
						<p>
							<?php esc_html_e( 'Your site currently has language switching for the login page disabled.', 'sitepress' ); ?>
							<button type="button" class="button wpml-login-activate">
								<?php /* translators: Link text that opens the plugins screen so an add-on can be turned on. Verb, imperative. */ esc_html_e( 'Activate', 'sitepress' ); ?>
							</button>
							<span class="spinner" style="float: none"></span>
						</p>
					</div>
					<div id="wpml-login-translation-updated" style="display:none">
						<?php echo sprintf( $message, $settingsPageLink ); ?>
					</div>
					<script type="text/javascript">
						jQuery(function ($) {
							$('.wpml-login-activate').click(function () {
								$(this).prop('disabled', true);
								$(this).parent().find('.spinner').css('visibility', 'visible');
								$.ajax({
									url: ajaxurl,
									type: "POST",
									data: {
										action: 'wpml_ajx_icl_login_page_translation',
										_icl_nonce: $('#icl_login_page_translation_nonce').val(),
										login_page_translation: 1
									},
									success: function (response) {
										$('#wpml-login-translation').hide();
										$('#wpml-login-translation-updated').css('display', 'block');
									}
								});
							});
						});
					</script>
				</td>
			</tr>
			<?php
		}
	}

	private function flush_user_language_cache( $user_id = null ) {
		if ( $user_id ) {
			wp_cache_delete( $user_id, 'user_meta' );
			wp_cache_delete( $user_id, WPML_User_Admin_Language::CACHE_GROUP );
		} elseif (
			function_exists( 'wp_cache_supports' )
			&& wp_cache_supports( 'flush_group' )
		) {
			wp_cache_flush_group( 'user_meta' );
			wp_cache_flush_group( WPML_User_Admin_Language::CACHE_GROUP );
		}
	}
}
