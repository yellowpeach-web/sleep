<?php
defined( 'ABSPATH' ) || exit;

$wpml_view_strings = isset( $strings ) ? $strings : null;
$wpml_view_active_service = isset( $active_service ) ? $active_service : null;
$wpml_view_nonces = isset( $nonces ) ? $nonces : null;
$wpml_view_needs_info_refresh = isset( $needs_info_refresh ) ? $needs_info_refresh : null;
$wpml_view_service_description = isset( $service_description ) ? $service_description : null;
?>
<div class="js-wpml-active-service-wrapper">
    <a id="js-wpml-active-service-wrapper"></a>
    <div class="js-ts-refreshing-message ts-refreshing-message" style="display:none">
        <p><span class="spinner is-active"></span><?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'refreshing_ts_message' ) ); ?></p>
    </div>
    <div class="ts-active-service js-ts-active-service">
        <input type="hidden" class="active-service-title" value="<?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'modal_title' ) ); ?>">
        <input type="hidden" class="active-service-header" value="<?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'modal_header' ) ); ?>">
        <input type="hidden" class="active-service-tip" value="<?php echo wpml_view_esc( sprintf( wpml_view_string( wpml_view_attr( $wpml_view_strings, 'modal_tip' ) ), "<a target='_blank' href='" . wpml_view_esc( wpml_view_attr( $wpml_view_active_service, 'get_url' ), 'html_attr' ) . "'>" . wpml_view_esc( wpml_view_attr( $wpml_view_active_service, 'get_name' ) ) . '</a>' ) ); ?>">
        <input type="hidden" class="js-needs-info-refresh" value="<?php echo wpml_view_esc( $wpml_view_needs_info_refresh ); ?>">
        <input type="hidden" class="js-ts-refresh-nonce" value="<?php echo wpml_view_esc( wpml_view_attr( $wpml_view_nonces, 'refresh_ts_info' ) ); ?>">
        <div class="ts-admin-section-leftcol">
            <h3><?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'title' ) ); ?></h3>
            <div class="service-logo">
                <a href="<?php echo wpml_view_esc( wpml_view_attr( $wpml_view_active_service, 'get_url' ) ); ?>" target="_blank" rel="noopener">
                    <img src="<?php echo wpml_view_esc( wpml_view_attr( $wpml_view_active_service, 'get_logo_url' ) ); ?>" alt="<?php echo wpml_view_esc( wpml_view_attr( $wpml_view_active_service, 'get_name' ) ); ?>"/>
                </a>
            </div>
            <p>
                <button data-id="<?php echo wpml_view_esc( wpml_view_attr( $wpml_view_active_service, 'get_id' ) ); ?>"  class="wpml-antd-button wpml-antd-button-secondary js-deactivate-service"><?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'deactivate' ) ); ?></button>
            </p>
        </div>

        <div class="ts-admin-section-rightcol">

            <?php if ( wpml_view_attr( $wpml_view_active_service, 'get_has_language_pairs' ) && wpml_view_attr( $wpml_view_active_service, 'get_custom_fields_data' ) ) : ?>
                <div class="refresh-language-pairs-section">
                    <div class="spinner"></div>
                    <div class="wpml_ajax_response"></div>
                    <a class="button-secondary js-refresh-language-pairs" data-nonce="<?php echo wpml_view_esc( wpml_view_attr( $wpml_view_nonces, 'wpml-tp-refresh-language-pairs' ) ); ?>">
                        <?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'refresh_language_pairs' ) ); ?>

                    </a>
                </div>
            <?php endif; ?>

            <h3><?php echo wpml_view_esc( wpml_view_attr( $wpml_view_active_service, 'get_name' ) ); ?></h3>
            <p><?php echo wpml_view_raw( $wpml_view_service_description ); ?></p>

            <p><a target="_blank" href="<?php echo wpml_view_esc( wpml_view_attr( $wpml_view_active_service, 'get_doc_url' ) ); ?>"><?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'documentation' ) ); ?></a></p>

            <?php if ( 1 == wpml_view_attr( $wpml_view_active_service, 'get_requires_authentication' ) ) : ?>
                <div class="ts-authentication notice notice-warning inline clearfix">

                    <?php if ( wpml_view_attr( $wpml_view_active_service, 'get_custom_fields_data' ) ) : ?>
                        <p><?php echo wpml_view_raw( wpml_view_attr( wpml_view_attr( $wpml_view_strings, 'authentication' ), 'is_authorized' ) ); ?></p>
                        <p class="notice-action-link">
                            <button data-id="<?php echo wpml_view_esc( wpml_view_attr( $wpml_view_active_service, 'get_id' ) ); ?>" data-custom-fields="<?php echo wpml_view_esc( json_encode( wpml_view_attr( $wpml_view_active_service, 'get_custom_fields' ) ) ); ?>" class="js-invalidate-service button-primary"><?php echo wpml_view_esc( wpml_view_attr( wpml_view_attr( $wpml_view_strings, 'authentication' ), 'de_authorize_button' ) ); ?></button>
                            <button data-id="<?php echo wpml_view_esc( wpml_view_attr( $wpml_view_active_service, 'get_id' ) ); ?>" data-custom-fields="<?php echo wpml_view_esc( json_encode( wpml_view_attr( $wpml_view_active_service, 'get_custom_fields' ) ) ); ?>" class="js-update-service-credentials button-primary"><?php echo wpml_view_esc( wpml_view_attr( wpml_view_attr( $wpml_view_strings, 'authentication' ), 'update_credentials_button' ) ); ?></button>
                        </p>
                    <?php else: ?>
                        <p><?php echo wpml_view_raw( sprintf( wpml_view_string( wpml_view_attr( wpml_view_attr( $wpml_view_strings, 'authentication' ), 'description' ) ), wpml_view_esc( wpml_view_attr( $wpml_view_active_service, 'get_name' ) ), '<a target="_blank" href="' . wpml_view_esc( wpml_view_attr( $wpml_view_active_service, 'get_doc_url' ), 'html_attr' ) . '">' . wpml_view_string( wpml_view_attr( $wpml_view_strings, 'documentation_lower' ) ) . '</a>' ) ); ?></p>
                        <p class="notice-action-link"><button class="js-authenticate-service wpml-antd-button wpml-antd-button-primary" data-id="<?php echo wpml_view_esc( wpml_view_attr( $wpml_view_active_service, 'get_id' ) ); ?>" data-custom-fields="<?php echo wpml_view_esc( json_encode( wpml_view_attr( $wpml_view_active_service, 'get_custom_fields' ) ) ); ?>"><?php echo wpml_view_esc( wpml_view_attr( wpml_view_attr( $wpml_view_strings, 'authentication' ), 'authenticate_button' ) ); ?></button></p>
                    <?php endif; ?>

                </div>
            <?php endif; ?>

        </div>
    </div>
</div>
