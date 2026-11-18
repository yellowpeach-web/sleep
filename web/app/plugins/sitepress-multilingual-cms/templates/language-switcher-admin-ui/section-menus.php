<?php
defined( 'ABSPATH' ) || exit;

$settings = wpml_view_attr( $model, 'settings' );
$data     = wpml_view_attr( $model, 'data' );
$strings  = wpml_view_attr( $model, 'strings' );
$menus    = wpml_view_attr( $data, 'menus' );
$tooltips = wpml_view_attr( $strings, 'tooltips' );
$texts    = wpml_view_attr( $strings, 'menus' );
?>
<?php $slug_placeholder = '%id%'; ?>

<?php $context = array_merge( $model, array( 'slug_placeholder' => $slug_placeholder ) ); ?>
<?php
$wpml_view->partial(
	'table-slots.twig',
	array_merge(
		$context,
		array(
			'slot_type'      => 'menus',
			'slots_settings' => wpml_view_attr( $settings, 'menus' ),
			'slots'          => $menus,
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
			'existing_items'       => is_array( $menus ) ? count( $menus ) : 0,
			'settings_items'       => is_array( wpml_view_attr( $settings, 'menus' ) ) ? count( wpml_view_attr( $settings, 'menus' ) ) : 0,
			'tooltip_all_assigned' => wpml_view_attr( $tooltips, 'add_menu_all_assigned' ),
			'tooltip_no_item'      => wpml_view_attr( $tooltips, 'add_menu_no_menu' ),
			'button_target'        => '#wpml-ls-new-menus-template',
			'button_label'         => wpml_view_attr( $texts, 'add_button_label' ),
		)
	)
);
?>

<script type="text/html" id="wpml-ls-new-menus-template" class="js-wpml-ls-template">
    <div class="js-wpml-ls-subform wpml-ls-subform" data-title="<?php echo wpml_view_esc( wpml_view_attr( $texts, 'dialog_title_new' ) ); ?>" data-item-slug="<?php echo wpml_view_esc( $slug_placeholder ); ?>" data-item-type="menus">

        <?php
        $wpml_view->partial(
            'slot-subform-menus.twig',
            array_merge(
                $context,
                array(
                    'slug'           => $slug_placeholder,
                    'slots_settings' => wpml_view_attr( $model, 'slots_settings' ),
                    'slots'          => $menus,
                    'preview'        => wpml_view_attr( wpml_view_attr( wpml_view_attr( $model, 'previews' ), 'menu' ), wpml_view_attr( $model, 'slug' ) ),
                )
            )
        );
        ?>
    </div>
</script>

<script type="text/html" id="wpml-ls-new-menus-row-template" class="js-wpml-ls-template">
    <?php
    $wpml_view->partial(
        'table-slot-row.twig',
        array_merge(
            $context,
            array(
                'slug'  => $slug_placeholder,
                'slots' => wpml_view_attr( $model, 'menus' ),
            )
        )
    );
    ?>
</script>