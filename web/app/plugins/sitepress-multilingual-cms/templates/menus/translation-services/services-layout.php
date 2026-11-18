<?php
defined( 'ABSPATH' ) || exit;

$wpml_view_nonces = isset( $nonces ) ? $nonces : null;
$wpml_view_has_active_service = isset( $has_active_service ) ? $has_active_service : null;
$wpml_view_strings = isset( $strings ) ? $strings : null;
$wpml_view_active_service = isset( $active_service ) ? $active_service : null;
$wpml_view_has_preferred_service = isset( $has_preferred_service ) ? $has_preferred_service : null;
$wpml_view_has_services = isset( $has_services ) ? $has_services : null;
$wpml_view_services = isset( $services ) ? $services : null;
$wpml_view_endpoints = isset( $endpoints ) ? $endpoints : null;
$wpml_view_lsp_logo_placeholder = isset( $lsp_logo_placeholder ) ? $lsp_logo_placeholder : null;
$wpml_view_select_endpoint = isset( $select_endpoint ) ? $select_endpoint : null;
$wpml_view_translate_everything = isset( $translate_everything ) ? $translate_everything : null;
$wpml_view_settings_url = isset( $settings_url ) ? $settings_url : null;
?>
<?php foreach ( is_array( $wpml_view_nonces ) ? $wpml_view_nonces : array() as $wpml_view_key => $wpml_view_nonce ) : ?>
    <input type="hidden" class="<?php echo wpml_view_esc( $wpml_view_key ); ?>" name="<?php echo wpml_view_esc( $wpml_view_key ); ?>" value="<?php echo wpml_view_esc( $wpml_view_nonce ); ?>">
<?php endforeach; ?>
<details class="wpml-tm-ts-admin-section wpml-section wpml-ts-collapsible"<?php if ( $wpml_view_has_active_service ) : ?> open<?php endif; ?>>
    <summary class="wpml-ts-collapsible__summary">
        <h2><?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'translation_services' ) ); ?></h2>
        <svg class="wpml-ts-collapsible__caret" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M9 5l7 7-7 7"/>
        </svg>
    </summary>

    <div class="wpml-ts-description">
        <p>
            <?php echo wpml_view_raw( wpml_view_attr( $wpml_view_strings, 'translation_services_description' ) ); ?>

        </p>
        <button id="add-unlisted-translation-service" class="ant-btn ant-btn-tertiary wpml-antd-button wpml-antd-button-tertiary wpml-ts-text-xs"><?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'enable_unlisted_translation_service' ) ); ?></button>
    </div>

    <?php echo wpml_view_raw( $wpml_view_active_service ); ?>


    <?php if ( false == $wpml_view_has_preferred_service && $wpml_view_has_services ) : ?>

        <div
                id="wpml-tp-services"
                data-services="<?php echo wpml_view_esc( json_encode( $wpml_view_services ) ); ?>"
                data-endpoints="<?php echo wpml_view_esc( json_encode( $wpml_view_endpoints ) ); ?>"
                data-logoPlaceholder="<?php echo wpml_view_esc( $wpml_view_lsp_logo_placeholder ); ?>"
                data-selectendpoint="<?php echo wpml_view_esc( json_encode( $wpml_view_select_endpoint ) ); ?>"
                data-translate-everything="<?php echo wpml_view_esc( $wpml_view_translate_everything ); ?>"
                data-settings-url="<?php echo wpml_view_esc( $wpml_view_settings_url ); ?>"
        >
        </div>

        <div class="js-bottom-section">
            <hr class="wpml-margin-top-base">
            <p><?php echo wpml_view_esc( wpml_view_attr( wpml_view_attr( $wpml_view_strings, 'ts' ), 'different' ) ); ?> <a target="_blank" class="wpml-external-link" href="<?php echo wpml_view_esc( wpml_view_attr( wpml_view_attr( $wpml_view_strings, 'ts' ), 'tell_us_url' ) ); ?>"><?php echo wpml_view_esc( wpml_view_attr( wpml_view_attr( $wpml_view_strings, 'ts' ), 'tell_us' ) ); ?></a></p>
        </div>

    <?php endif; ?>

    <?php if ( false == $wpml_view_has_services ) : ?>
        <div class="warning notice-warning otgs-notice">
            <?php $wpml_view_no_service_found = wpml_view_attr( $wpml_view_strings, 'no_service_found' ); ?><?php foreach ( is_array( $wpml_view_no_service_found ) ? $wpml_view_no_service_found : array() as $wpml_view_no_service_message ) : ?>
                <p><?php echo wpml_view_raw( sprintf( wpml_view_string( $wpml_view_no_service_message ), "<a target='_blank' href='" . wpml_view_string( wpml_view_attr( $wpml_view_strings, 'support_link' ) ) . "'>" . wpml_view_string( wpml_view_attr( $wpml_view_strings, 'wpml_support' ) ) . '</a>' ) ); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</details>
