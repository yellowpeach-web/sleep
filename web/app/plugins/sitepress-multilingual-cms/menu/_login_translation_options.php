<?php
global $sitepress, $sitepress_settings;
?>
<div class="wpml-section" id="ml-content-setup-sec-wp-login">

	<div class="wpml-section-header">
		<h3><?php esc_html_e( 'Login and registration pages', 'sitepress' ); ?></h3>
	</div>

	<div class="wpml-section-content">
		<form id="icl_login_page_translation" name="icl_login_page_translation" action="">
			<?php
			wpml_nonce_field_with_id( 'icl_login_page_translation_nonce', '_icl_nonce', 'icl_login_page_translation_nonce' );
			?>
			<p>
				<label for="login_page_translation">
					<input class="wpml-checkbox-native" type="checkbox" id="login_page_translation"
						   name="login_page_translation"
						<?php checked( get_option( \WPML\UrlHandling\WPLoginUrlConverter::SETTINGS_KEY, false ) ); ?>
						   value="1"/>
					<?php esc_html_e( 'Allow translating the login and registration pages', 'sitepress' ); ?>
				</label>
                <br/>
                <p class="sub-section" id="show_login_page_language_switcher_sub_section"
				<?php if ( ! get_option( \WPML\UrlHandling\WPLoginUrlConverter::SETTINGS_KEY, false ) ) : ?> style="display: none" <?php endif; ?>
                >
                    <label for="show_login_page_language_switcher">
                        <input class="wpml-checkbox-native" type="checkbox" id="show_login_page_language_switcher"
                               name="show_login_page_language_switcher"
                            <?php checked( get_option( \WPML\AdminLanguageSwitcher\AdminLanguageSwitcher::LANGUAGE_SWITCHER_KEY, true ) ); ?>
                               value="1"/>
                        <?php esc_html_e( 'Show Language Switcher on login and registration pages', 'sitepress' ); ?>
                    </label>
                </p>
			</p>
			<?php
			$language_negotiation_type = (int) $sitepress->get_setting( 'language_negotiation_type' );
			if ( WPML_LANGUAGE_NEGOTIATION_TYPE_DIRECTORY === $language_negotiation_type ) :
				$nginx_rewrite_rules = '';
				foreach ( array_keys( $sitepress->get_active_languages() ) as $language_code ) {
					$nginx_rewrite_rules .= 'rewrite ^/' . $language_code . '/wp-login.php /wp-login.php break;' . "\n";
				}
				?>
				<div class="notice-info notice below-h2">
					<p>
						<?php /* translators: Notice on WPML settings. nginx is a product name and stays as it is. */ esc_html_e( 'If your site uses nginx, please add a rewrite rule for each language.', 'sitepress' ); ?>
						<?php /* translators: Second sentence of the same notice, explaining why the rules are needed. */ esc_html_e( 'nginx does not read the .htaccess file. Without these rules, the login and registration pages return a 404 error in secondary languages.', 'sitepress' ); ?>
					</p>
					<details>
						<summary><?php /* translators: Click target on WPML settings that opens the nginx rewrite rules for this site. It is the user's own question. */ esc_html_e( 'Which rules do I add?', 'sitepress' ); ?></summary>
						<pre><?php echo esc_html( $nginx_rewrite_rules ); ?></pre>
					</details>
				</div>
			<?php endif; ?>
			<div class="wpml-section-content-inner">
				<p class="buttons-wrap">
					<span class="icl_ajx_response" id="icl_ajx_response_login"></span>
					<input class="button-primary wpml-button base-btn" name="save" value="<?php /* translators: Button label that keeps what was entered. Verb, imperative. */ esc_attr_e( 'Save', 'sitepress' ); ?>"
						   type="submit"/>
				</p>
			</div>

		</form>

	</div> <!-- wpml-section-content -->

</div> <!-- .wpml-section -->
