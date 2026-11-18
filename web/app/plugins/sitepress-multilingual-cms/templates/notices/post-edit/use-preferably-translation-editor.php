<?php
defined( 'ABSPATH' ) || exit;

$wpml_view_nonce = isset( $nonce ) ? $nonce : null;
$wpml_view_warning_line_1 = isset( $warning_line_1 ) ? $warning_line_1 : null;
$wpml_view_warning_line_2 = isset( $warning_line_2 ) ? $warning_line_2 : null;
$wpml_view_warning_line_3 = isset( $warning_line_3 ) ? $warning_line_3 : null;
$wpml_view_go_back_button = isset( $go_back_button ) ? $go_back_button : null;
$wpml_view_translation_editor_url = isset( $translation_editor_url ) ? $translation_editor_url : null;
$wpml_view_open_in_te_button = isset( $open_in_te_button ) ? $open_in_te_button : null;
$wpml_view_edit_anyway_button = isset( $edit_anyway_button ) ? $edit_anyway_button : null;
$wpml_view_do_not_show_again_action = isset( $do_not_show_again_action ) ? $do_not_show_again_action : null;
$wpml_view_do_not_show_again = isset( $do_not_show_again ) ? $do_not_show_again : null;
?>
<div id="wpml-tm-post-edit-alert" class="js-wpml-tm-post-edit-alert wpml-tm-post-edit-alert" style="display: none;">
    <?php echo wpml_view_raw( $wpml_view_nonce ); ?>

    <p class="js-wpml-tm-warning-line-1"><i class="otgs-ico otgs-ico-warning-o"></i> <?php echo wpml_view_raw( $wpml_view_warning_line_1 ); ?></p>
    <p><?php echo wpml_view_raw( $wpml_view_warning_line_2 ); ?></p>
    <p><strong><?php echo wpml_view_raw( $wpml_view_warning_line_3 ); ?></strong></p>
    <p class="text-center action-buttons">
        <button class="js-wpml-tm-go-back wpml-button base-btn wpml-button--outlined"><?php echo wpml_view_esc( $wpml_view_go_back_button ); ?></button>
        <a class="js-wpml-tm-open-in-te wpml-button base-btn" href="<?php echo wpml_view_esc( $wpml_view_translation_editor_url ); ?>"><?php echo wpml_view_esc( $wpml_view_open_in_te_button ); ?></a>
        <button class="js-wpml-tm-continue wpml-button base-btn button-alert"><?php echo wpml_view_esc( $wpml_view_edit_anyway_button ); ?></button>
    </p>
    <p style="display: none;">
        <label class="wpml-checkbox">
            <input class="do-not-show-again" type="checkbox" value="1" data-action="<?php echo wpml_view_esc( $wpml_view_do_not_show_again_action ); ?>">
            <span><?php echo wpml_view_esc( $wpml_view_do_not_show_again ); ?></span>
        </label>
    </p>
</div>
