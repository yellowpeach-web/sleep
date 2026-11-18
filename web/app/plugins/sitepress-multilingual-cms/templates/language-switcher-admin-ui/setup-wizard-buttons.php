<?php
defined( 'ABSPATH' ) || exit;

$misc = wpml_view_attr( wpml_view_attr( $model, 'strings' ), 'misc' );
?>
<?php if ( ! wpml_view_attr( $model, 'setup_complete' ) ) : ?>
	<footer id="icl_setup_nav_3" class="js-wpml-ls-section wpml-section clearfix text-right">
		<input id="icl_setup_back_2" class="button-secondary alignleft" name="save" value="<?php echo wpml_view_esc( wpml_view_attr( $misc, 'button_back' ) ); ?>" type="button" />
		<?php echo wpml_view_raw( wpml_view_attr( $model, 'setup_step_2_nonce_field' ) ); ?>

		<input class="button-primary alignright" name="save" value="<?php echo wpml_view_esc( wpml_view_attr( $misc, 'button_next' ) ); ?>" type="submit" />
		<input type="hidden" name="submit_setup_wizard" value="0" />
	</footer>
<?php endif; ?>