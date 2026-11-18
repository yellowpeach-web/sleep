<?php
defined( 'ABSPATH' ) || exit;

$misc = wpml_view_attr( wpml_view_attr( $model, 'strings' ), 'misc' );
?>
<div id="wpml-ls-dialog" style="display:none;" >
    <div class="js-wpml-ls-dialog-inner">

    </div>
    <div class="wpml-dialog-footer ">
        <span class="errors icl_error_text"></span>
        <input
            class="js-wpml-ls-dialog-close cancel wpml-dialog-close-button alignleft wpml-button base-btn gray-light-btn"
            value="<?php echo wpml_view_esc( wpml_view_attr( $misc, 'button_cancel' ) ); ?>"
            type="button"
        >
        <input class="button-primary js-wpml-ls-dialog-save wpml-button base-btn term-save alignright" value="<?php echo wpml_view_esc( wpml_view_attr( $misc, 'button_save' ) ); ?>" type="submit">
        <span class="spinner alignright"></span>
    </div>
</div>
