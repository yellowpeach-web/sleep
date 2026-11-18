<?php
defined( 'ABSPATH' ) || exit;

$wpml_view_strings = isset( $strings ) ? $strings : null;
$wpml_view_nonce = isset( $nonce ) ? $nonce : null;
?>
<h3><?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'title' ) ); ?></h3>
<div class="wpml-job-migration-error" style="display: none;">
    <p><strong><?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'communicationError' ) ); ?></strong></p>
    <p class="error-message"></p>
</div>
<p><?php echo wpml_view_raw( wpml_view_attr( $wpml_view_strings, 'description' ) ); ?></p>
<?php if ( wpml_view_attr( $wpml_view_strings, 'button' ) ) : ?>
    <button style="float:left" class="button-primary wpml-start-jobs-migration"><?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'button' ) ); ?></button>
    <div style="float:left" class="spinner wpml-jobs-migration-spinner"></div>
    <div style="float: left; margin-left: 10px; display:none" class="wpml-js-jobs-migration-progress">
        <span class="wpml-js-migrated-jobs">20</span> <?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'of' ) ); ?> <span class="wpml-js-total-jobs">100</span> <?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'jobs_migrated' ) ); ?>

    </div>
    <?php echo wpml_view_raw( $wpml_view_nonce ); ?>

<?php endif; ?>
<div class="clearfix"></div>