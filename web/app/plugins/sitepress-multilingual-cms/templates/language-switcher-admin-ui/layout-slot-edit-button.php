<?php
defined( 'ABSPATH' ) || exit;

ob_start();
?>
<?php if ( 'edit' == wpml_view_attr( $model, 'action' ) ) : ?>
	<?php $class = 'otgs-ico-edit'; ?>
<?php else : ?>
	<?php $class = 'otgs-ico-add'; ?>
<?php endif; ?>

<p class="wpml-ls-slot-management-link-wrapper">
	<a href="<?php echo wpml_view_esc( wpml_view_attr( $model, 'url' ) ); ?>" class="js-wpml-ls-slot-management-link button-secondary">
		<span class="<?php echo wpml_view_esc( $class ); ?>"></span> <?php echo wpml_view_esc( wpml_view_attr( $model, 'label' ) ); ?></a>
</p>
<?php
echo wpml_view_raw( trim( preg_replace( '/>\s+</', '><', (string) ob_get_clean() ) ) );