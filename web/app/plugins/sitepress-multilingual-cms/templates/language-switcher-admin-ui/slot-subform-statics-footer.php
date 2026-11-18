<?php
defined( 'ABSPATH' ) || exit;

$settings = wpml_view_attr( wpml_view_attr( $model, 'settings' ), 'statics' );
$footer   = wpml_view_attr( $settings, 'footer' );
?>
<?php $wpml_view->partial( 'preview.twig', array_merge( $model, array( 'preview' => wpml_view_attr( wpml_view_attr( wpml_view_attr( $model, 'previews' ), 'statics' ), 'footer' ) ) ) ); ?>

<div class="wpml-ls-subform-options">

	<?php
	$wpml_view->partial(
		'dropdown-templates.twig',
		array_merge(
			$model,
			array(
				'id'        => 'in-footer',
				'name'      => 'statics[footer][template]',
				'value'     => wpml_view_attr( $footer, 'template' ),
				'slot_type' => 'footer',
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
				'name_base'     => 'statics[footer]',
				'slot_settings' => $footer,
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
				'id'            => 'static-footer',
				'name_base'     => 'statics[footer]',
				'slot_settings' => $footer,
			)
		)
	);
	?>

</div>