<?php
defined( 'ABSPATH' ) || exit;

$wpml_view_theme_localization_load_textdomain = isset( $theme_localization_load_textdomain ) ? $theme_localization_load_textdomain : null;
$wpml_view_gettext_theme_domain_name = isset( $gettext_theme_domain_name ) ? $gettext_theme_domain_name : null;
?>
<div class="automatically_load_mo_file clearfix">
    <ul>
        <li>
            <label for="theme_localization_load_textdomain">
                <input <?php echo wpml_view_esc( wpml_view_attr( $wpml_view_theme_localization_load_textdomain, 'checked' ) ); ?>

                        type="checkbox" class="wpml-checkbox-native" id="theme_localization_load_textdomain"
                        name="theme_localization_load_textdomain"
                        value="<?php echo wpml_view_esc( wpml_view_attr( $wpml_view_theme_localization_load_textdomain, 'value' ) ); ?>"
                >
                <?php echo wpml_view_esc( wpml_view_attr( $wpml_view_theme_localization_load_textdomain, 'label' ) ); ?>

            </label>
        </li>
        <li class="wpml-gettext-theme-domain-name">
            <?php echo wpml_view_esc( wpml_view_attr( $wpml_view_gettext_theme_domain_name, 'label' ) ); ?>

            <input type="text"
                   name="gettext_theme_domain_name"
                   value="<?php echo wpml_view_esc( wpml_view_attr( $wpml_view_gettext_theme_domain_name, 'value' ) ); ?>"
                   <?php if ( ! wpml_view_attr( $wpml_view_theme_localization_load_textdomain, 'checked' ) ) : ?> disabled="disabled"<?php endif; ?>
            >
        </li>
    </ul>
</div>
