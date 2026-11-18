<?php
defined( 'ABSPATH' ) || exit;


$wpml_view_section_label = isset( $section_label ) ? $section_label : null;
$wpml_view_nonce_field = isset( $nonce_field ) ? $nonce_field : null;
$wpml_view_nonce_value = isset( $nonce_value ) ? $nonce_value : null;
$wpml_view_top_options = isset( $top_options ) ? $top_options : null;
$wpml_view_button_label = isset( $button_label ) ? $button_label : null;
?>
<div class="wpml-section">
    <div class="wpml-section-header">
        <h3><?php echo wpml_view_esc( $wpml_view_section_label ); ?></h3>
    </div>
    <div class="wpml-section-content">
        <input type="hidden" id="<?php echo wpml_view_esc( $wpml_view_nonce_field ); ?>" name="<?php echo wpml_view_esc( $wpml_view_nonce_field ); ?>" value="<?php echo wpml_view_esc( $wpml_view_nonce_value ); ?>">
        <div class="top">

            <ul>
            <?php foreach ( is_array( $wpml_view_top_options ) ? $wpml_view_top_options : array() as $wpml_view_option ) : ?>
                <li>
                    <?php if ( wpml_view_attr( $wpml_view_option, 'template' ) ) : ?>
                        <?php $wpml_view->partial( wpml_view_attr( $wpml_view_option, 'template' ), is_array( wpml_view_attr( $wpml_view_option, 'model' ) ) ? wpml_view_attr( $wpml_view_option, 'model' ) : array() ); ?>
                    <?php else: ?>
                        <label for="<?php echo wpml_view_esc( wpml_view_attr( $wpml_view_option, 'name' ) ); ?>">
                            <input type="<?php echo wpml_view_esc( wpml_view_attr( $wpml_view_option, 'type' ) ); ?>" <?php echo wpml_view_esc( wpml_view_attr( $wpml_view_option, 'checked' ) ); ?>

                                   name="<?php echo wpml_view_esc( wpml_view_attr( $wpml_view_option, 'name' ) ); ?>" id="<?php echo wpml_view_esc( wpml_view_attr( $wpml_view_option, 'name' ) ); ?>" class="wpml-<?php echo wpml_view_esc( wpml_view_attr( $wpml_view_option, 'type' ) ); ?>-native"
                                   value="<?php echo wpml_view_esc( wpml_view_attr( $wpml_view_option, 'value' ) ); ?>"
                            >
                            <?php echo wpml_view_esc( wpml_view_attr( $wpml_view_option, 'label' ) ); ?>

                        </label>

                        <?php if ( wpml_view_attr( $wpml_view_option, 'tooltip' ) ) : ?>
                            <a class="otgs-ico-help js-otgs-popover-tooltip"
                               data-tippy-zindex="999999"
                               title="<?php echo wpml_view_esc( wpml_view_attr( $wpml_view_option, 'tooltip' ) ); ?>"></a>
                        <?php endif; ?>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
            </ul>

        </div>
        <input id="wpml-js-theme-plugin-save-option" type="button" class="other-options-save wpml-button base-btn" value="<?php echo wpml_view_esc( $wpml_view_button_label ); ?>">
        <span id="wpml-js-theme-plugin-options-spinner" class="spinner"></span><span class="wpml-ajax-response" id="wpml-js-theme-plugin-options-response"></span>
    </div>
</div>

<?php $wpml_view->partial( 'scanning-progress.twig', array( 'scanning_progress_msg' => wpml_view_attr( $model, 'scanning_progress_msg' ), 'scanning_results_title' => wpml_view_attr( $model, 'scanning_results_title' ) ) ); ?>
