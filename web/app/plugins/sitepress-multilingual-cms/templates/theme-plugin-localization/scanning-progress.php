<?php
defined( 'ABSPATH' ) || exit;

$wpml_view_scanning_results_title = isset( $scanning_results_title ) ? $scanning_results_title : null;
$wpml_view_scanning_progress_msg = isset( $scanning_progress_msg ) ? $scanning_progress_msg : null;
?>
<div class="wpml-scanning-progress" title="<?php echo wpml_view_esc( $wpml_view_scanning_results_title ); ?>">
    <span style="display: none" class="wpml-scanning-progress-msg"><?php echo wpml_view_esc( $wpml_view_scanning_progress_msg ); ?></span><div class="spinner"></div>
    <div class="wpml-scanning-results"></div>
</div>
