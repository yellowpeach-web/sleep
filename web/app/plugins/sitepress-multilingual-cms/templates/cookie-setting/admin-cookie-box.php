<?php
defined( 'ABSPATH' ) || exit;

$wpml_view_strings = isset( $strings ) ? $strings : null;
$wpml_view_ajax_response_id = isset( $ajax_response_id ) ? $ajax_response_id : null;
$wpml_view_nonce_field = isset( $nonce_field ) ? $nonce_field : null;
$wpml_view_nonce_value = isset( $nonce_value ) ? $nonce_value : null;
$wpml_view_checked = isset( $checked ) ? $checked : null;
?>
<div class="wpml-section wpml-section-cookie wpml-settings" id="cookie">
    <div class="wpml-section-header">
        <h3><?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'title' ) ); ?></h3>
    </div>
    <div class="wpml-section-content">
        <form id="wpml_cookie_form" name="wpml_cookie_form" action="">
            <input type="hidden" id="<?php echo wpml_view_esc( $wpml_view_nonce_field ); ?>" name="<?php echo wpml_view_esc( $wpml_view_nonce_field ); ?>" value="<?php echo wpml_view_esc( $wpml_view_nonce_value ); ?>">
            <p>
                <label>
                    <input class="wpml-checkbox-native" type="checkbox" name="<?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'field_name' ) ); ?>" <?php echo wpml_view_esc( $wpml_view_checked ); ?> value="1" />
                    <?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'field_label' ) ); ?>

                    <a class="otgs-ico-help js-otgs-popover-tooltip"
                       data-tippy-zindex="999999"
                       title="<?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'tooltip' ), 'html_attr' ); ?>"></a>
                </label>
            </p>
            <p class="buttons-wrap">
                <span id="js-store-frontend-cookie-spinner" class="spinner"></span><span class="icl_ajx_response" id="<?php echo wpml_view_esc( $wpml_view_ajax_response_id ); ?>"></span>
                <input class="button-primary wpml-button base-btn" id="<?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'button_id' ) ); ?>" name="save" value="<?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'button_text' ) ); ?>" type="button" />
            </p>
        </form>
    </div>
</div>
