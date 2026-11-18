<?php
defined( 'ABSPATH' ) || exit;

$wpml_view_warning = isset( $warning ) ? $warning : null;
?>
<div class="error fade">
    <p id="icl_side_by_site">
        <?php echo wpml_view_raw( $wpml_view_warning ); ?>

    </p>
</div>
