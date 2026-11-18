<?php
defined( 'ABSPATH' ) || exit;

$wpml_view_registration = isset( $registration ) ? $registration : null;
?>
<div class="wpml-tm-nositekey-button-wrapper">
    <a class="button-primary button-lg" href="<?php echo wpml_view_esc( wpml_view_attr( $wpml_view_registration, 'link' ) ); ?>"><?php echo wpml_view_esc( wpml_view_attr( $wpml_view_registration, 'text' ) ); ?></a>
</div>