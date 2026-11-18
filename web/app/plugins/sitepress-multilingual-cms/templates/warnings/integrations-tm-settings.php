<?php
defined( 'ABSPATH' ) || exit;

$wpml_view_strings = isset( $strings ) ? $strings : null;
$wpml_view_nonces = isset( $nonces ) ? $nonces : null;
?>
<h3>
    <?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'title' ) ); ?>

</h3>

<p>
    <?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'message' ) ); ?>

</p>
<input type="hidden" name="wpml_set_translation_editor_nonce" value="<?php echo wpml_view_esc( wpml_view_attr( $wpml_view_nonces, 'enable' ) ); ?>">
<p class="js-done" style="display: none"><strong><?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'enable_done' ) ); ?></strong></p>
<p class="js-error" style="display: none"><strong><?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'enable_error' ) ); ?></strong></p>