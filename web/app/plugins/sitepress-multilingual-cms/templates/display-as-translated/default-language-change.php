<?php
defined( 'ABSPATH' ) || exit;

$wpml_view_lang_has_changed = isset( $lang_has_changed ) ? $lang_has_changed : null;
$wpml_view_after_message = isset( $after_message ) ? $after_message : null;
$wpml_view_after_help_link = isset( $after_help_link ) ? $after_help_link : null;
$wpml_view_after_help_text = isset( $after_help_text ) ? $after_help_text : null;
$wpml_view_before_message = isset( $before_message ) ? $before_message : null;
$wpml_view_before_help_link = isset( $before_help_link ) ? $before_help_link : null;
$wpml_view_before_help_text = isset( $before_help_text ) ? $before_help_text : null;
$wpml_view_got_it = isset( $got_it ) ? $got_it : null;
?>
<?php if ( $wpml_view_lang_has_changed ) : ?>
    <div class="notice notice-info inline is-dismissible otgs-is-dismissible wpml-default-lang-after-message">
        <p>
            <?php echo wpml_view_esc( $wpml_view_after_message ); ?> <a class="wpml-external-link" target="_blank" href="<?php echo wpml_view_esc( $wpml_view_after_help_link ); ?>"><?php echo wpml_view_esc( $wpml_view_after_help_text ); ?></a>.
            <span class="notice-dismiss" ></span>
        </p>
    </div>
<?php endif; ?>

<div style="display: none" class="notice notice-warning otgs-notice-icon inline wpml-default-lang-before-message">
    <i class="otgs-ico-warning"></i>
    <div>
        <p>
            <?php echo wpml_view_esc( $wpml_view_before_message ); ?> <a class="wpml-external-link" target="_blank" href="<?php echo wpml_view_esc( $wpml_view_before_help_link ); ?>"><?php echo wpml_view_esc( $wpml_view_before_help_text ); ?></a>.
        </p>
        <p>
            <label class="wpml-display-block"><input type="checkbox" class="display-as-translated-changing-default-language"> <?php echo wpml_view_esc( $wpml_view_got_it ); ?></label>
        </p>
    </div>
</div>