<?php
defined( 'ABSPATH' ) || exit;

$footer = wpml_view_attr( wpml_view_attr( wpml_view_attr( $model, 'settings' ), 'statics' ), 'footer' );
?>
<p class="wpml-ls-form-line js-wpml-ls-option">
    <label for="wpml-ls-show-in-footer">
        <input type="checkbox" id="wpml-ls-show-in-footer" name="statics[footer][show]" value="1"
               class="wpml-checkbox-native js-wpml-ls-toggle-slot js-wpml-ls-trigger-save" data-target=".js-wpml-ls-footer-toggle-target"
               <?php if ( wpml_view_attr( $footer, 'show' ) ) : ?>checked="checked"<?php endif; ?>/>
        <?php echo wpml_view_esc( wpml_view_attr( wpml_view_attr( wpml_view_attr( $model, 'strings' ), 'footer' ), 'show' ) ); ?>

    </label>

	<?php $wpml_view->partial( 'save-notification.twig', $model ); ?>
</p>

<div class="js-wpml-ls-footer-toggle-target<?php if ( 1 != wpml_view_attr( $footer, 'show' ) ) : ?> hidden<?php endif; ?>">

	<?php $slot_settings = array(); ?>
	<?php $slot_settings = array_merge( $slot_settings, array( 'footer' => $footer ) ); ?>

	<?php
	$wpml_view->partial(
		'table-slots.twig',
		array_merge(
			$model,
			array(
				'slot_settings'  => $slot_settings,
				'slot_type'      => 'statics',
				'slots_settings' => $slot_settings,
				'slug'           => 'footer',
			)
		)
	);
	?>

</div>
