<?php
defined( 'ABSPATH' ) || exit;

$slug          = wpml_view_attr( $model, 'slug' );
$slot_settings = wpml_view_attr( $model, 'slot_settings' );
?>
<?php if ( ! array_key_exists( 'slot_settings', $model ) ) : ?>
	<?php $slot_settings = wpml_view_attr( $model, 'default_menus_slot' ); ?>
<?php endif; ?>

<?php $context = array_merge( $model, array( 'slot_settings' => $slot_settings ) ); ?>
<?php $wpml_view->partial( 'preview.twig', array_merge( $context, array( 'preview' => wpml_view_attr( $model, 'preview' ) ) ) ); ?>

<div class="wpml-ls-subform-options">

    <?php
    $wpml_view->partial(
        'dropdown-menus.twig',
        array_merge(
            $context,
            array(
                'slug'  => $slug,
                'menus' => wpml_view_attr( $model, 'slots' ),
            )
        )
    );
    ?>

    <?php
    $wpml_view->partial(
        'dropdown-templates.twig',
        array_merge(
            $context,
            array(
                'id'        => 'in-menus-' . $slug,
                'name'      => 'menus[' . $slug . '][template]',
                'value'     => wpml_view_attr( $slot_settings, 'template' ),
                'slot_type' => 'menus',
            )
        )
    );
    ?>

    <?php
    $wpml_view->partial(
        'radio-position-menu.twig',
        array_merge(
            $context,
            array(
                'name_base'     => 'menus[' . $slug . ']',
                'slot_settings' => $slot_settings,
            )
        )
    );
    ?>

    <?php
    $wpml_view->partial(
        'radio-hierarchical-menu.twig',
        array_merge(
            $context,
            array(
                'name_base'     => 'menus[' . $slug . ']',
                'slot_settings' => $slot_settings,
            )
        )
    );
    ?>


    <?php
    $wpml_view->partial(
        'checkboxes-includes.twig',
        array_merge(
            $context,
            array(
                'name_base'     => 'menus[' . $slug . ']',
                'slot_settings' => $slot_settings,
                'template_slug' => wpml_view_attr( $slot_settings, 'template' ),
            )
        )
    );
    ?>

    <?php
    $wpml_view->partial(
        'panel-colors.twig',
        array_merge(
            $context,
            array(
                'id'            => 'in-menus-' . $slug,
                'name_base'     => 'menus[' . $slug . ']',
                'slot_settings' => $slot_settings,
                'slot_type'     => 'menus',
            )
        )
    );
    ?>

</div>