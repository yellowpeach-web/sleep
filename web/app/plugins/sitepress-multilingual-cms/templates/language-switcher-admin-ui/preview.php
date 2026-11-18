<?php
defined( 'ABSPATH' ) || exit;

$class   = wpml_view_attr( $model, 'class' );
$slot_preview = wpml_view_attr( $model, 'preview' );
?>
<div class="js-wpml-ls-preview-wrapper wpml-ls-preview-wrapper<?php if ( $class ) : ?> <?php echo wpml_view_esc( $class ); ?><?php endif; ?>">
    <strong class="wpml-ls-preview-label"><?php echo wpml_view_esc( wpml_view_attr( wpml_view_attr( wpml_view_attr( $model, 'strings' ), 'misc' ), 'label_preview' ) ); ?></strong>
    <span class="spinner"></span>
    <div class="js-wpml-ls-preview"><?php echo wpml_view_raw( wpml_view_attr( $slot_preview, 'html' ) ); ?></div>
</div>