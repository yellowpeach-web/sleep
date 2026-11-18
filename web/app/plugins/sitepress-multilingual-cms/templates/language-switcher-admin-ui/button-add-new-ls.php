<?php
defined( 'ABSPATH' ) || exit;

$existing_items = wpml_view_attr( $model, 'existing_items' );
$settings_items = wpml_view_attr( $model, 'settings_items' );
$disabled       = wpml_view_attr( $model, 'disabled' );
?>
<p class="alignright">

	<?php $add_tooltip = wpml_view_attr( $model, 'tooltip_all_assigned' ); ?>

	<?php if ( $existing_items == 0 ) : ?>
		<?php $add_tooltip = wpml_view_attr( $model, 'tooltip_no_item' ); ?>
	<?php endif; ?>

	<?php if ( $settings_items >= $existing_items ) : ?>
		<?php $disabled = true; ?>
	<?php endif; ?>

	<span class="js-wpml-ls-tooltip-wrapper<?php if ( ! $disabled ) : ?> hidden<?php endif; ?>">
        <?php $wpml_view->partial( 'tooltip.twig', array_merge( $model, array( 'content' => $add_tooltip ) ) ); ?>
    </span>

	<button class="button-secondary js-wpml-ls-open-dialog wpml-button base-btn wpml-button--outlined"<?php if ( $disabled ) : ?> disabled="disabled"<?php endif; ?>
			data-target="<?php echo wpml_view_esc( wpml_view_attr( $model, 'button_target' ) ); ?>">+ <?php echo wpml_view_esc( wpml_view_attr( $model, 'button_label' ) ); ?></button>
</p>
