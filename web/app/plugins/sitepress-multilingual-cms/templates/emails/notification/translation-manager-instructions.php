<?php
defined( 'ABSPATH' ) || exit;
?>
<p><?php echo wpml_view_raw( isset( $intro_message_1 ) ? $intro_message_1 : null ); ?></p>
<p><?php echo wpml_view_raw( isset( $intro_message_2 ) ? $intro_message_2 : null ); ?></p>
<p><strong><a href="<?php echo wpml_view_esc( isset( $setup_url ) ? $setup_url : null ); ?>"><?php echo wpml_view_esc( isset( $setup ) ? $setup : null ); ?></a></strong></p>
<p><?php echo wpml_view_raw( isset( $reminder ) ? $reminder : null ); ?></p>
<p>
    <?php echo wpml_view_esc( isset( $at_your_service ) ? $at_your_service : null ); ?>,<br />
    <?php echo wpml_view_esc( isset( $admin_name ) ? $admin_name : null ); ?><br />
    <?php echo wpml_view_raw( isset( $admin_for_site ) ? $admin_for_site : null ); ?>

</p>
