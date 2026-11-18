<?php
defined( 'ABSPATH' ) || exit;

$slot = wpml_view_attr( wpml_view_attr( wpml_view_attr( $model, 'settings' ), 'statics' ), 'shortcode_actions' );
?>
<?php $wpml_view->partial( 'preview.twig', array_merge( $model, array( 'preview' => wpml_view_attr( wpml_view_attr( wpml_view_attr( $model, 'previews' ), 'statics' ), 'shortcode_actions' ) ) ) ); ?>

<div class="wpml-ls-subform-options">

	<?php
	$wpml_view->partial(
		'dropdown-templates.twig',
		array_merge(
			$model,
			array(
				'id'        => 'in-shortcode-action',
				'name'      => 'statics[shortcode_actions][template]',
				'value'     => wpml_view_attr( $slot, 'template' ),
				'slot_type' => 'shortcode_actions',
			)
		)
	);
	?>

	<?php
	$wpml_view->partial(
		'checkboxes-includes.twig',
		array_merge(
			$model,
			array(
				'id'            => 'in-shortcode-actions',
				'name_base'     => 'statics[shortcode_actions]',
				'slot_settings' => $slot,
				'template_slug' => wpml_view_attr( wpml_view_attr( $model, 'slot_settings' ), 'template' ),
			)
		)
	);
	?>

	<?php
	$wpml_view->partial(
		'panel-colors.twig',
		array_merge(
			$model,
			array(
				'id'            => 'in-shortcode-actions',
				'name_base'     => 'statics[shortcode_actions]',
				'slot_settings' => $slot,
			)
		)
	);
	?>

</div>