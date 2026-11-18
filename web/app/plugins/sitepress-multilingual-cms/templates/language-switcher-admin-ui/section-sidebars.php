<?php
defined( 'ABSPATH' ) || exit;

$settings = wpml_view_attr( $model, 'settings' );
$data     = wpml_view_attr( $model, 'data' );
$strings  = wpml_view_attr( $model, 'strings' );
$sidebars = wpml_view_attr( $data, 'sidebars' );
$tooltips = wpml_view_attr( $strings, 'tooltips' );
$texts    = wpml_view_attr( $strings, 'sidebars' );
?>
<?php $slug_placeholder = '%id%'; ?>

<?php $context = array_merge( $model, array( 'slug_placeholder' => $slug_placeholder ) ); ?>
<?php
$wpml_view->partial(
	'table-slots.twig',
	array_merge(
		$context,
		array(
			'slot_type'      => 'sidebars',
			'slots_settings' => wpml_view_attr( $settings, 'sidebars' ),
			'slots'          => $sidebars,
		)
	)
);
?>

<?php
$wpml_view->partial(
	'button-add-new-ls.twig',
	array_merge(
		$context,
		array(
			'existing_items'       => is_array( $sidebars ) ? count( $sidebars ) : 0,
			'settings_items'       => is_array( wpml_view_attr( $settings, 'sidebars' ) ) ? count( wpml_view_attr( $settings, 'sidebars' ) ) : 0,
			'tooltip_all_assigned' => wpml_view_attr( $tooltips, 'add_sidebar_all_assigned' ),
			'tooltip_no_item'      => wpml_view_attr( $tooltips, 'add_sidebar_no_sidebar' ),
			'button_target'        => '#wpml-ls-new-sidebars-template',
			'button_label'         => wpml_view_attr( $texts, 'add_button_label' ),
		)
	)
);
?>

<script type="text/html" id="wpml-ls-new-sidebars-template" class="js-wpml-ls-template">
    <div class="js-wpml-ls-subform wpml-ls-subform" data-title="<?php echo wpml_view_esc( wpml_view_attr( $texts, 'dialog_title_new' ) ); ?>" data-item-slug="<?php echo wpml_view_esc( $slug_placeholder ); ?>" data-item-type="sidebars">

        <?php
        $wpml_view->partial(
            'slot-subform-sidebars.twig',
            array_merge(
                $context,
                array(
                    'slug'           => $slug_placeholder,
                    'slots_settings' => wpml_view_attr( $model, 'slots_settings' ),
                    'slots'          => $sidebars,
                    'preview'        => wpml_view_attr( wpml_view_attr( wpml_view_attr( $model, 'previews' ), 'sidebars' ), wpml_view_attr( $model, 'slug' ) ),
                )
            )
        );
        ?>

    </div>
</script>

<script type="text/html" id="wpml-ls-new-sidebars-row-template" class="js-wpml-ls-template">
    <?php
    $wpml_view->partial(
        'table-slot-row.twig',
        array_merge(
            $context,
            array(
                'slug'  => $slug_placeholder,
                'slots' => wpml_view_attr( $model, 'sidebars' ),
            )
        )
    );
    ?>
</script>