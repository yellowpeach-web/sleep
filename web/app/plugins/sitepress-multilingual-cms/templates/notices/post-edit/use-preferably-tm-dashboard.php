<?php
defined( 'ABSPATH' ) || exit;

$wpml_view_warning = isset( $warning ) ? $warning : null;
$wpml_view_use_tm_dashboard = isset( $use_tm_dashboard ) ? $use_tm_dashboard : null;
?>
<div class="error">
    <p><?php echo wpml_view_raw( $wpml_view_warning ); ?></p>
    <p><?php echo wpml_view_raw( $wpml_view_use_tm_dashboard ); ?></p>
</div>'