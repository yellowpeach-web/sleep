<?php

namespace WPML\AdminLanguageSwitcher;

class AdminLanguageSwitcherRenderer {
	public static function render( $languageOptions ) {
		?>
        <div class="wpml-login-ls">
            <form id="wpml-login-ls-form" action="" method="get">
				<?php if ( isset( $_GET['redirect_to'] ) && '' !== $_GET['redirect_to'] ) { ?>
                    <input type="hidden" name="redirect_to" value="<?php echo esc_url_raw( $_GET['redirect_to'] ); ?>"/>
				<?php } ?>

				<?php if ( isset( $_GET['action'] ) && '' !== $_GET['action'] ) { ?>
                    <input type="hidden" name="action" value="<?php echo esc_attr( $_GET['action'] ); ?>"/>
				<?php } ?>

                <label for="language-switcher-locales">
                    <span class="dashicons dashicons-translation" aria-hidden="true"></span>
                    <span class="screen-reader-text"><?php /* translators: Column heading and field label in the WPML admin, for the language of a piece of content. Noun, singular. */ esc_html_e( 'Language', 'sitepress' ); ?></span>
                </label>
                <select name="wpml_lang" id="wpml-language-switcher-locales">
					<?php
					echo implode( '', $languageOptions );
					?>
                </select>
                <input type="submit" class="button" value="<?php /* translators: Button label beside the language dropdown of the WPML admin language switcher: apply the language that was picked. Verb, imperative. */ esc_attr_e( 'Change', 'sitepress' ); ?>">

            </form>
        </div>
		<?php
	}
}